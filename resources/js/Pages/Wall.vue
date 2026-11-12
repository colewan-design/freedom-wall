<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import DirectoryLayout from '../Layouts/DirectoryLayout.vue';
import TurnstileWidget from '../Components/TurnstileWidget.vue';
import { timeAgo } from '../lib/date';
import {
  ACCEPTED_UPLOAD_TYPES,
  describeAttachments,
  isVideoUrl,
  MAX_VIDEO_SECONDS,
  validateAttachments,
} from '../lib/media';
import { hashtagError, MAX_HASHTAG_LENGTH, normalizeHashtag } from '../lib/hashtag';
import { EXTERNAL_APPS } from '../lib/apps';

// The rail teases a few; /tools lists every one of them.
const SIDEBAR_APPS = EXTERNAL_APPS.slice(0, 4);

// The layout is rendered here rather than through defineOptions because the
// page fills the shell's #rail slot, and Inertia's persistent-layout option
// only ever hands a page to the default slot.

const props = defineProps({
  posts: { type: Array, required: true },
  // Hashtag list comes from Submission::CATEGORIES so the chips can never drift
  // from what the form request accepts.
  categories: { type: Array, default: () => [] },
  // Totals for the whole wall, counted in SQL. Deriving them from `posts` would
  // only ever describe the most recent 50 rows the page actually renders.
  stats: {
    type: Object,
    default: () => ({ total: 0, withPhotos: 0, textOnly: 0, today: 0 }),
  },
  // The best-scoring posts per time window, for the leaderboard tabs.
  postBoard: {
    type: Object,
    default: () => ({ today: [], week: [], month: [], all: [] }),
  },
  // Every vote ever cast, for the leaderboard headline.
  voteTotal: { type: Number, default: 0 },
  // All-time hashtag counts, for the directory in the rail.
  tagCounts: { type: Array, default: () => [] },
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.success ?? null);
const supportNote = computed(() => page.props.flash?.support ?? null);
const composerTextarea = ref(null);
const fileInput = ref(null);
const composerModalOpen = ref(false);

const totalPosts = computed(() => props.stats.total ?? 0);
const withPhotos = computed(() => props.stats.withPhotos ?? 0);
const textOnly = computed(() => props.stats.textOnly ?? 0);
const postsToday = computed(() => props.stats.today ?? 0);

const composerForm = useForm({
  content: '',
  category: '',
  images: [],
  captchaToken: null,
});

const customTagOpen = ref(false);
const customTag = ref('');
const customTagInput = ref(null);
const customTagError = computed(() => hashtagError(customTag.value));

const composerReady = computed(
  () =>
    composerForm.content.trim().length > 0 &&
    composerForm.category !== '' &&
    customTagError.value === null,
);

const attachmentSummary = computed(() => describeAttachments(composerForm.images));

async function onComposerFileChange(e) {
  const files = Array.from(e.target.files || []);
  const error = await validateAttachments(files);

  if (error) {
    composerForm.images = [];
    if (fileInput.value) fileInput.value.value = '';
    composerForm.setError('images', error);
    return;
  }

  composerForm.clearErrors('images');
  composerForm.images = files;
}

function selectCategory(category) {
  composerForm.category = category;
  customTagOpen.value = false;
  customTag.value = '';
  composerForm.clearErrors('category');
}

function toggleCustomTag() {
  customTagOpen.value = !customTagOpen.value;
  // Opening deselects whichever chip was active, so the two never disagree
  // about which single tag the post is going out with.
  composerForm.category = customTagOpen.value ? normalizeHashtag(customTag.value) : '';
  composerForm.clearErrors('category');

  if (customTagOpen.value) {
    nextTick(() => customTagInput.value?.focus());
  } else {
    customTag.value = '';
  }
}

function onCustomTagInput() {
  customTag.value = normalizeHashtag(customTag.value);
  composerForm.category = customTag.value;
  composerForm.clearErrors('category');
}

function onComposerSubmit() {
  if (!composerForm.content.trim()) {
    composerForm.setError('content', 'Please write something before submitting.');
    return;
  }
  if (!composerForm.category) {
    composerForm.setError('category', 'Please pick or write a hashtag for your post.');
    return;
  }
  if (customTagError.value) {
    composerForm.setError('category', customTagError.value);
    return;
  }
  if (composerForm.captchaToken === null) {
    composerForm.setError('captchaToken', 'Please complete the CAPTCHA challenge.');
    return;
  }

  composerForm.post(route('submissions.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      composerForm.reset();
      customTag.value = '';
      customTagOpen.value = false;
      if (fileInput.value) fileInput.value.value = '';
      closeComposerModal();
    },
  });
}

function openComposerModal() {
  composerModalOpen.value = true;
  nextTick(() => composerTextarea.value?.focus());
}

function closeComposerModal() {
  composerModalOpen.value = false;
}

onMounted(() => {
  if (window.location.hash === '#composer') {
    openComposerModal();
  }
});

// The shell's search box writes straight into this ref, so filtering stays
// here on the page that owns the posts.
const search = ref('');

const filteredPosts = computed(() => {
  const query = search.value.trim().toLowerCase();
  if (!query) return props.posts;
  return props.posts.filter((post) => post.content?.toLowerCase().includes(query));
});

// --- Votes -----------------------------------------------------------------
// The server owns the score. Each row starts from what the page was given and
// is corrected by whatever the vote endpoint returns, so two tabs cannot drift
// apart for longer than one click.

const votes = reactive({});

function voteFor(post) {
  if (!votes[post.id]) {
    votes[post.id] = { score: Number(post.score ?? 0), yours: Number(post.your_vote ?? 0) };
  }
  return votes[post.id];
}

// Seed from props up front: a watcher would miss the posts the page opened on.
// The board is seeded too, so a post sitting in both shares one entry and the
// two copies can never show different numbers.
watch(
  () => [props.posts, props.postBoard],
  () => {
    const seed = (post) => {
      votes[post.id] = { score: Number(post.score ?? 0), yours: Number(post.your_vote ?? 0) };
    };
    props.posts.forEach(seed);
    Object.values(props.postBoard ?? {}).forEach((rows) => rows.forEach(seed));
  },
  { immediate: true, deep: true },
);

const voting = reactive({});

async function castVote(post, value) {
  if (voting[post.id]) return;

  const vote = voteFor(post);
  const before = { ...vote };

  // Pressing the lit arrow takes the vote back; the other one flips it.
  const next = vote.yours === value ? 0 : value;
  vote.score += next - vote.yours;
  vote.yours = next;
  voting[post.id] = true;

  try {
    const { data } = await window.axios.post(route('submissions.vote', post.id), { value });
    vote.score = Number(data.score);
    vote.yours = Number(data.your_vote);
  } catch (error) {
    Object.assign(vote, before);
    voteError.value = error.response?.status === 429
      ? 'You are voting too fast. Give it a moment.'
      : 'That vote did not go through. Try again.';
    window.clearTimeout(voteErrorTimer);
    voteErrorTimer = window.setTimeout(() => { voteError.value = ''; }, 4000);
  } finally {
    voting[post.id] = false;
  }
}

const voteError = ref('');
let voteErrorTimer = null;

// --- Saves -----------------------------------------------------------------
// A personal bookmark with no account behind it, kept on the device the same
// way the forum keeps its saved threads.

const savedPosts = ref([]);

onMounted(() => {
  try {
    const stored = JSON.parse(localStorage.getItem('wall-saved') ?? '[]');
    savedPosts.value = Array.isArray(stored) ? stored : [];
  } catch {
    savedPosts.value = [];
  }
});

function isSaved(post) {
  return savedPosts.value.includes(post.id);
}

function toggleSave(post) {
  savedPosts.value = isSaved(post)
    ? savedPosts.value.filter((id) => id !== post.id)
    : [...savedPosts.value, post.id];

  try {
    localStorage.setItem('wall-saved', JSON.stringify(savedPosts.value));
  } catch {
    // A browser with storage blocked still gets the in-page state.
  }
}

function postCategory(post) {
  return post.content?.match(/#([\w-]+)\s*$/i)?.[1] ?? 'community';
}

function postBody(post) {
  return post.content?.replace(/\s*#[\w-]+\s*$/i, '').trim() ?? '';
}

async function sharePost(post) {
  const url = `${window.location.origin}/wall#post-${post.id}`;
  if (navigator.share) {
    try {
      await navigator.share({ title: 'BSU Freedom Wall', text: post.content, url });
      return;
    } catch {
      // user cancelled or share failed, fall through to clipboard
    }
  }
  await navigator.clipboard?.writeText(url);
}

// --- Hashtag furniture -----------------------------------------------------
// The leaderboard, the featured card and the rail directory all label posts by
// their hashtag, so the blurbs and the tile letters are defined once here.

function tagInitial(tag) {
  return (tag ?? '?').charAt(0).toUpperCase();
}

function formatCount(value) {
  return Number(value ?? 0).toLocaleString();
}

const BOARD_TABS = [
  { id: 'today', label: 'Today' },
  { id: 'week', label: 'This week' },
  { id: 'month', label: 'This month' },
  { id: 'all', label: 'All time' },
];

// A quiet day would otherwise open the page on an empty board.
const boardTab = ref(props.postBoard?.today?.length ? 'today' : 'all');
const boardRows = computed(() => props.postBoard?.[boardTab.value] ?? []);
const boardTabLabel = computed(
  () => BOARD_TABS.find((tab) => tab.id === boardTab.value)?.label.toLowerCase() ?? '',
);

// The server counted the row with this browser's vote as it stood on page
// load; swap that out for the live one so voting moves the tally at once.
function boardTally(row) {
  const was = Number(row.your_vote ?? 0);
  const now = voteFor(row).yours;
  return {
    up: Number(row.upvotes ?? 0) - (was === 1 ? 1 : 0) + (now === 1 ? 1 : 0),
    down: Number(row.downvotes ?? 0) - (was === -1 ? 1 : 0) + (now === -1 ? 1 : 0),
  };
}

// A board row links to its post only when that post is one of the fifty the
// feed rendered; an older one has no anchor on the page to jump to.
const feedIds = computed(() => new Set(props.posts.map((post) => post.id)));

function applyTag(tag) {
  search.value = search.value === tag ? '' : tag;
  document.querySelector('#latest')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// --- Post of the day -------------------------------------------------------
// Seeded on the date so everyone sees the same pick all day, and drawn from the
// newest posts so the slot keeps moving instead of pinning one post forever.

const today = new Date();
const todayLabel = today.toLocaleDateString(undefined, {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  year: 'numeric',
});

const featuredPost = computed(() => {
  const pool = props.posts.slice(0, 20);
  if (pool.length === 0) return null;
  const seed = today.getFullYear() * 10000 + (today.getMonth() + 1) * 100 + today.getDate();
  return pool[seed % pool.length];
});

const proofTags = computed(() => props.posts.slice(0, 4).map((post) => tagInitial(postCategory(post))));

function excerpt(text, length = 80) {
  const clean = (text ?? '').replace(/\s+/g, ' ').trim();
  return clean.length > length ? `${clean.slice(0, length).trim()}…` : clean;
}

// --- Feed plumbing ---------------------------------------------------------

const expandedPosts = reactive({});
const overflowingPosts = reactive({});
const contentEls = {};

function registerContent(id) {
  return (el) => {
    if (el) contentEls[id] = el;
  };
}

function checkContentOverflow(id) {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const el = contentEls[id];
      if (!el || expandedPosts[id]) return;
      overflowingPosts[id] = el.scrollHeight > el.clientHeight + 2;
    });
  });
}

function toggleExpand(post) {
  expandedPosts[post.id] = !expandedPosts[post.id];
  if (!expandedPosts[post.id]) {
    nextTick(() => checkContentOverflow(post.id));
  }
}

watch(
  filteredPosts,
  (posts) => {
    nextTick(() => {
      posts.forEach((post) => checkContentOverflow(post.id));
    });
  },
  { immediate: true },
);

// The feed is dealt across columns rather than laid out row by row: a grid row
// is as tall as its tallest card, which left a hole under every short post once
// one of its neighbours carried a photo. Dealing 1 top-left, 2 top-right, 3
// under 1 keeps the newest at the top while each column packs to its own height.
const wallEl = ref(null);
const columnCount = ref(2);
let columnObserver = null;

onMounted(() => {
  if (!window.ResizeObserver || !wallEl.value) return;
  columnObserver = new ResizeObserver(([entry]) => {
    columnCount.value = entry.contentRect.width < 720 ? 1 : 2;
  });
  columnObserver.observe(wallEl.value);
});

onBeforeUnmount(() => columnObserver?.disconnect());

const feedColumns = computed(() => {
  const columns = Array.from({ length: columnCount.value }, () => []);
  filteredPosts.value.forEach((post, index) => {
    columns[index % columnCount.value].push({ post, index });
  });
  return columns;
});

// Entrance stagger caps out a few steps down the page so late cards don't crawl in.
function riseDelay(index) {
  return { '--i': Math.min(index, 4) };
}

const lightbox = reactive({ open: false, images: [], index: 0 });

function openLightbox(post, index) {
  lightbox.images = post.image_urls;
  lightbox.index = index;
  lightbox.open = true;
}

function closeLightbox() {
  lightbox.open = false;
}

function nextImage() {
  lightbox.index = (lightbox.index + 1) % lightbox.images.length;
}

function prevImage() {
  lightbox.index = (lightbox.index - 1 + lightbox.images.length) % lightbox.images.length;
}

function onLightboxKeydown(e) {
  if (e.key === 'Escape' && composerModalOpen.value) {
    closeComposerModal();
    return;
  }
  if (!lightbox.open) return;
  if (e.key === 'Escape') closeLightbox();
  else if (e.key === 'ArrowRight') nextImage();
  else if (e.key === 'ArrowLeft') prevImage();
}

onMounted(() => window.addEventListener('keydown', onLightboxKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onLightboxKeydown));
</script>

<template>
  <Head title="News Feed" />

  <DirectoryLayout v-model:search="search">
    <div ref="wallEl" class="wall">
      <!-- Support note ------------------------------------------------------
           Shown when a submission read as someone in trouble. It sits on the
           page rather than in the composer because the composer closes the
           moment a post goes through, and this is the one message that has to
           be seen. The post itself was accepted and queued either way. -->
      <aside v-if="supportNote" class="support-note rise" :style="riseDelay(0)">
        <p class="support-lede">{{ supportNote.message }}</p>
        <ul>
          <li v-for="line in supportNote.lines" :key="line">{{ line }}</li>
        </ul>
      </aside>

      <!-- Hero ------------------------------------------------------------ -->
      <header class="hero rise" :style="riseDelay(0)">
        <h1>Anonymous posts from the BSU community</h1>
        <p class="hero-lede">
          Confessions, rants, questions, and kilig — written without a name,
          read by a human before it goes up, then posted for everyone.
        </p>

        <div class="hero-actions">
          <button type="button" class="btn btn-green" @click="openComposerModal">
            Write a post <span aria-hidden="true">&rarr;</span>
          </button>
          <Link href="/threads" class="btn btn-plain">Browse threads</Link>

          <p v-if="proofTags.length" class="hero-proof">
            <span class="proof-stack" aria-hidden="true">
              <i v-for="(initial, index) in proofTags" :key="index">{{ initial }}</i>
            </span>
            <span>+{{ formatCount(totalPosts) }} posts</span>
          </p>
        </div>
      </header>

      <!-- Post of the day -------------------------------------------------- -->
      <section v-if="featuredPost" class="spotlight rise" :style="riseDelay(1)">
        <div class="spotlight-head">
          <span class="spotlight-flag">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="m12 3 2.6 5.6 6.1.8-4.5 4.2 1.2 6-5.4-3-5.4 3 1.2-6L3.3 9.4l6.1-.8L12 3Z" />
            </svg>
            Featured post of the day
          </span>
          <span class="spotlight-date">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3.5" y="5" width="17" height="15" rx="2" stroke="currentColor" stroke-width="1.6" />
              <path d="M3.5 10h17M8 3.5v3M16 3.5v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
            {{ todayLabel }}
          </span>
        </div>

        <div class="spotlight-body">
          <span class="tile tile-lg" aria-hidden="true">{{ tagInitial(postCategory(featuredPost)) }}</span>

          <div class="spotlight-text">
            <p class="meta-line">
              <b>#{{ postCategory(featuredPost) }}</b>
              <i aria-hidden="true">•</i>
              <span>Anonymous</span>
            </p>
            <h2>{{ postBody(featuredPost) }}</h2>
            <p class="spotlight-sub">Posted {{ timeAgo(featuredPost.reviewed_at) }}</p>
          </div>

          <span class="vote">
            <button
              type="button"
              class="vote-arrow"
              :class="{ on: voteFor(featuredPost).yours === 1 }"
              :aria-pressed="voteFor(featuredPost).yours === 1"
              aria-label="Upvote this post"
              @click="castVote(featuredPost, 1)"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="m6 14 6-6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <b :class="{ up: voteFor(featuredPost).score > 0, down: voteFor(featuredPost).score < 0 }">
              {{ voteFor(featuredPost).score }}
            </b>
            <button
              type="button"
              class="vote-arrow"
              :class="{ on: voteFor(featuredPost).yours === -1 }"
              :aria-pressed="voteFor(featuredPost).yours === -1"
              aria-label="Downvote this post"
              @click="castVote(featuredPost, -1)"
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="m6 10 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </span>

          <a :href="`#post-${featuredPost.id}`" class="btn btn-green btn-sm">
            Read post <span aria-hidden="true">&rarr;</span>
          </a>
        </div>
      </section>

      <!-- Hashtag leaderboard ---------------------------------------------- -->
      <section class="board rise" :style="riseDelay(2)" aria-labelledby="board-heading">
        <div class="board-head">
          <div>
            <p id="board-heading" class="card-kicker">Community leaderboard</p>
            <p class="board-total">{{ formatCount(voteTotal) }} {{ voteTotal === 1 ? 'vote' : 'votes' }} cast</p>
          </div>

          <div class="board-tabs" role="group" aria-label="Leaderboard period">
            <button
              v-for="tab in BOARD_TABS"
              :key="tab.id"
              type="button"
              :aria-pressed="boardTab === tab.id"
              :class="{ active: boardTab === tab.id }"
              @click="boardTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>
        </div>

        <ol v-if="boardRows.length" class="board-list">
          <li v-for="(row, index) in boardRows" :key="row.id" :class="{ lead: index < 3 }">
            <span class="board-rank">{{ index + 1 }}</span>
            <span class="tile" aria-hidden="true">{{ tagInitial(postCategory(row)) }}</span>

            <div class="board-text">
              <p class="meta-line">
                <b>#{{ postCategory(row) }}</b>
                <i aria-hidden="true">•</i>
                <span>Anonymous</span>
              </p>
              <a v-if="feedIds.has(row.id)" class="board-name" :href="`#post-${row.id}`">
                {{ excerpt(postBody(row), 64) }}
              </a>
              <p v-else class="board-name as-text">{{ excerpt(postBody(row), 64) }}</p>
              <p class="board-sub">
                <span class="board-posted">Posted {{ timeAgo(row.reviewed_at) }} · </span>
                <span class="tally-up">{{ boardTally(row).up }} up</span>
                · <span class="tally-down">{{ boardTally(row).down }} down</span>
              </p>
            </div>

            <span class="vote">
              <button
                type="button"
                class="vote-arrow"
                :class="{ on: voteFor(row).yours === 1 }"
                :aria-pressed="voteFor(row).yours === 1"
                aria-label="Upvote this post"
                @click="castVote(row, 1)"
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="m6 14 6-6 6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
              <b :class="{ up: voteFor(row).score > 0, down: voteFor(row).score < 0 }">{{ voteFor(row).score }}</b>
              <button
                type="button"
                class="vote-arrow"
                :class="{ on: voteFor(row).yours === -1 }"
                :aria-pressed="voteFor(row).yours === -1"
                aria-label="Downvote this post"
                @click="castVote(row, -1)"
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="m6 10 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </span>
          </li>
        </ol>

        <p v-else class="empty">
          {{ boardTab === 'all'
            ? 'No votes yet. Upvote a post and it lands here.'
            : `Nothing posted ${boardTabLabel} has been voted up yet.` }}
        </p>
      </section>

      <!-- Feed -------------------------------------------------------------- -->
      <div id="latest" class="feed-head rise" :style="riseDelay(3)">
        <div>
          <h2>Fresh from the wall</h2>
          <p>Newer submissions from the community, straight out of the review queue.</p>
        </div>
        <a href="#hashtags" class="link-green">Browse hashtags <span aria-hidden="true">&rarr;</span></a>
      </div>

      <section
        id="composer"
        class="composer-trigger rise"
        :style="riseDelay(3)"
        role="button"
        tabindex="0"
        aria-label="Create an anonymous post"
        @click="openComposerModal"
        @keydown.enter="openComposerModal"
        @keydown.space.prevent="openComposerModal"
      >
        <img src="/images/branding/bsufw-mark-64.png" alt="" class="composer-avatar" />
        <span class="composer-trigger-text">Share something anonymously…</span>
        <span class="composer-trigger-photo" aria-hidden="true">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.5" />
            <circle cx="8.5" cy="10" r="1.6" fill="currentColor" />
            <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span class="composer-post">Post</span>
      </section>

      <p v-if="voteError" class="vote-error" role="status">{{ voteError }}</p>

      <p v-if="filteredPosts.length === 0" class="empty">
        {{ posts.length === 0 ? 'No approved posts yet.' : 'No posts match your search.' }}
      </p>

      <div v-else class="feed-grid" :style="{ '--cols': columnCount }">
        <div v-for="(column, columnIndex) in feedColumns" :key="columnIndex" class="feed-col">
        <article
          v-for="{ post, index } in column"
          :id="`post-${post.id}`"
          :key="post.id"
          class="post rise"
          :class="{ 'is-featured': featuredPost && post.id === featuredPost.id }"
          :style="riseDelay(index + 4)"
        >
          <div class="post-head">
            <img src="/images/branding/bsufw-mark-64.png" alt="" class="post-avatar" />
            <div class="post-head-text">
              <span class="post-name">
                BSU Freedom Wall
                <svg class="post-verified" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M9.5 12.5 11 14l4-4.5M12 3.5l1.9 1.02 2.15-.35 1.06 1.9 1.9 1.06-.35 2.15L20.5 11l-1.02 1.9.35 2.15-1.9 1.06-1.06 1.9-2.15-.35L12 20.5l-1.9-1.02-2.15.35-1.06-1.9-1.9-1.06.35-2.15L3.5 12l1.02-1.9-.35-2.15 1.9-1.06 1.06-1.9 2.15.35L12 3.5Z"
                    fill="currentColor"
                  />
                  <path d="m9.2 12.4 1.8 1.8 3.8-4.2" stroke="var(--d-card)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="post-meta">
                <button type="button" class="post-tag" @click="applyTag(postCategory(post))">#{{ postCategory(post) }}</button>
                <i aria-hidden="true">•</i>
                <time>{{ timeAgo(post.reviewed_at) }}</time>
              </span>
            </div>
          </div>

          <div class="post-body">
            <p
              :ref="registerContent(post.id)"
              class="post-content"
              :class="{ clamped: !expandedPosts[post.id] }"
            >{{ postBody(post) }}</p>
            <button
              v-if="overflowingPosts[post.id] || expandedPosts[post.id]"
              type="button"
              class="post-seemore"
              @click="toggleExpand(post)"
            >
              {{ expandedPosts[post.id] ? 'see less' : 'see more' }}
            </button>
          </div>

          <div v-if="post.image_urls?.length" class="photo-grid" :class="`tiles-${Math.min(post.image_urls.length, 4)}`">
            <button
              v-for="(imageUrl, imageIndex) in post.image_urls.slice(0, 4)"
              :key="imageIndex"
              type="button"
              class="photo-tile"
              @click="openLightbox(post, imageIndex)"
            >
              <template v-if="isVideoUrl(imageUrl)">
                <video :src="imageUrl" class="photo-video" muted playsinline preload="metadata" />
                <span class="photo-play" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5-11-6.5Z" /></svg>
                </span>
              </template>
              <img v-else :src="imageUrl" alt="" loading="lazy" />
              <span v-if="imageIndex === 3 && post.image_urls.length > 4" class="photo-more">
                +{{ post.image_urls.length - 4 }}
              </span>
            </button>
          </div>

          <div class="post-actions">
            <span class="vote vote-inline">
              <button
                type="button"
                class="vote-arrow"
                :class="{ on: voteFor(post).yours === 1 }"
                :aria-pressed="voteFor(post).yours === 1"
                aria-label="Upvote this post"
                @click="castVote(post, 1)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="m6 14 6-6 6 6" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
              <b :class="{ up: voteFor(post).score > 0, down: voteFor(post).score < 0 }">{{ voteFor(post).score }}</b>
              <button
                type="button"
                class="vote-arrow"
                :class="{ on: voteFor(post).yours === -1 }"
                :aria-pressed="voteFor(post).yours === -1"
                aria-label="Downvote this post"
                @click="castVote(post, -1)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="m6 10 6 6 6-6" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
            </span>
            <button type="button" class="action" @click="sharePost(post)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="18" cy="5" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <circle cx="6" cy="12" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <circle cx="18" cy="19" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <path d="m8.1 10.8 7.8-4.6M8.1 13.2l7.8 4.6" stroke="currentColor" stroke-width="1.4" />
              </svg>
              <span>Share</span>
            </button>
            <button type="button" class="action" :class="{ active: isSaved(post) }" @click="toggleSave(post)">
              <svg width="14" height="14" viewBox="0 0 24 24" :fill="isSaved(post) ? 'currentColor' : 'none'" aria-hidden="true">
                <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
              </svg>
              <span>{{ isSaved(post) ? 'Saved' : 'Save' }}</span>
            </button>
          </div>
        </article>
        </div>
      </div>
    </div>

    <!-- Rail ---------------------------------------------------------------- -->
    <template #rail>
      <p class="rail-stat">
        <span>Wall posts</span>
        <strong>{{ formatCount(totalPosts) }}</strong>
        <i>· {{ postsToday }} today</i>
      </p>

      <section id="hashtags" class="rail-card">
        <div class="rail-head">
          <h2>Hashtags</h2>
          <button v-if="search" type="button" class="rail-all" @click="search = ''">Clear</button>
          <span v-else class="rail-all muted">All</span>
        </div>

        <div v-if="tagCounts.length" class="tag-grid">
          <button
            v-for="entry in tagCounts"
            :key="entry.tag"
            type="button"
            class="tag-cell"
            :class="{ active: search === entry.tag }"
            @click="applyTag(entry.tag)"
          >
            <span class="tile tile-sm" aria-hidden="true">{{ tagInitial(entry.tag) }}</span>
            <span class="tag-cell-text">
              <b>{{ entry.tag }}</b>
              <small>{{ formatCount(entry.count) }} posted</small>
            </span>
          </button>
        </div>
        <p v-else class="empty">No hashtags in use yet.</p>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Wall at a glance</h2>
        </div>
        <ul class="rail-stats">
          <li><span>Total posts</span><strong>{{ formatCount(totalPosts) }}</strong></li>
          <li><span>With media</span><strong>{{ formatCount(withPhotos) }}</strong></li>
          <li><span>Text only</span><strong>{{ formatCount(textOnly) }}</strong></li>
          <li><span>Posted today</span><strong>{{ formatCount(postsToday) }}</strong></li>
        </ul>
      </section>

      <section class="rail-card rail-promo">
        <h2>Before you post</h2>
        <ol class="rail-rules">
          <li><b>Be respectful.</b> No hate, harassment, or personal attacks.</li>
          <li><b>Keep names out of it.</b> Protect yourself and everyone else.</li>
          <li><b>Keep it real.</b> Genuine thoughts beat ragebait.</li>
          <li><b>Every post is read.</b> A human reviews it before it goes live.</li>
        </ol>
        <button type="button" class="btn btn-green btn-block" @click="openComposerModal">
          Write a post <span aria-hidden="true">&rarr;</span>
        </button>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Other tools</h2>
          <Link href="/tools" class="rail-all">See all</Link>
        </div>
        <ul class="rail-apps">
          <li v-for="app in SIDEBAR_APPS" :key="app.id">
            <a :href="app.url" target="_blank" rel="noopener noreferrer">
              <img v-if="app.logo" :src="app.logo" alt="" loading="lazy" decoding="async" />
              <span v-else class="tile tile-sm" aria-hidden="true">{{ app.mark }}</span>
              <span class="rail-app-text">
                <b>{{ app.name }}</b>
                <small>{{ app.host }}</small>
              </span>
            </a>
          </li>
        </ul>
      </section>
    </template>
  </DirectoryLayout>

  <Teleport to="body">
    <div v-if="lightbox.open" class="lightbox" @click.self="closeLightbox">
      <button type="button" class="lightbox-close" aria-label="Close" @click="closeLightbox">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
      </button>

      <button
        v-if="lightbox.images.length > 1"
        type="button"
        class="lightbox-nav prev"
        aria-label="Previous image"
        @click.stop="prevImage"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 5 8 12l7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <video
        v-if="isVideoUrl(lightbox.images[lightbox.index])"
        :key="lightbox.images[lightbox.index]"
        :src="lightbox.images[lightbox.index]"
        class="lightbox-image"
        controls
        autoplay
        playsinline
        @click.stop
      />
      <img v-else :src="lightbox.images[lightbox.index]" alt="" class="lightbox-image" />

      <button
        v-if="lightbox.images.length > 1"
        type="button"
        class="lightbox-nav next"
        aria-label="Next image"
        @click.stop="nextImage"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <div v-if="lightbox.images.length > 1" class="lightbox-counter">
        {{ lightbox.index + 1 }} / {{ lightbox.images.length }}
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="composerModalOpen" class="composer-modal-overlay" @click.self="closeComposerModal">
      <div class="composer-modal" role="dialog" aria-modal="true" aria-label="Create post">
        <div class="composer-modal-header">
          <h3>Create post</h3>
          <button type="button" class="composer-modal-close" aria-label="Close" @click="closeComposerModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <div class="composer-modal-user">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="composer-avatar" />
          <div class="composer-modal-user-text">
            <span class="composer-modal-name">BSU Freedom Wall</span>
            <span class="composer-modal-sub">Anonymous · reviewed before posting</span>
          </div>
        </div>

        <form class="composer-form" @submit.prevent="onComposerSubmit">
          <textarea
            ref="composerTextarea"
            v-model="composerForm.content"
            rows="5"
            placeholder="What's on your mind?"
            class="composer-modal-textarea"
          ></textarea>

          <div class="composer-tags">
            <p class="composer-tags-label">
              Pick a hashtag or write your own <span class="composer-tags-required">required</span>
            </p>
            <div class="composer-tag-row">
              <button
                v-for="category in categories"
                :key="category"
                type="button"
                class="composer-tag"
                :class="{ active: composerForm.category === category }"
                :aria-pressed="composerForm.category === category"
                @click="selectCategory(category)"
              >
                #{{ category }}
              </button>
              <button
                type="button"
                class="composer-tag composer-tag-custom"
                :class="{ active: customTagOpen }"
                :aria-pressed="customTagOpen"
                @click="toggleCustomTag"
              >
                + your own
              </button>
            </div>

            <div v-if="customTagOpen" class="composer-custom-tag">
              <span class="composer-custom-tag-hash" aria-hidden="true">#</span>
              <input
                ref="customTagInput"
                v-model="customTag"
                type="text"
                class="composer-custom-tag-input"
                :maxlength="MAX_HASHTAG_LENGTH"
                placeholder="your-hashtag"
                aria-label="Write your own hashtag"
                autocomplete="off"
                @input="onCustomTagInput"
              />
            </div>

            <p v-if="customTagError" class="composer-tags-error">{{ customTagError }}</p>
            <p v-else-if="composerForm.category" class="composer-tags-hint">
              #{{ composerForm.category }} will be added to the end of your post.
            </p>
          </div>

          <label class="composer-attach-row">
            <span>
              {{ composerForm.images.length ? `${attachmentSummary} selected` : 'Add photos or a video' }}
            </span>
            <span class="composer-attach-icon">
              <input
                ref="fileInput"
                type="file"
                :accept="ACCEPTED_UPLOAD_TYPES"
                multiple
                @change="onComposerFileChange"
              />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.4" />
                <circle cx="8.5" cy="10" r="1.6" fill="currentColor" />
                <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </label>

          <ul v-if="composerForm.images.length" class="composer-file-list">
            <li v-for="image in composerForm.images" :key="`${image.name}-${image.lastModified}`">{{ image.name }}</li>
          </ul>
          <p v-else class="composer-attach-hint">
            Up to 4 photos, or one video of {{ MAX_VIDEO_SECONDS }} seconds or less.
          </p>

          <TurnstileWidget @verified="(token) => (composerForm.captchaToken = token)" />

          <p v-if="composerForm.errors.content" class="composer-banner error">{{ composerForm.errors.content }}</p>
          <p v-else-if="composerForm.errors.category" class="composer-banner error">{{ composerForm.errors.category }}</p>
          <p v-else-if="composerForm.errors.captchaToken" class="composer-banner error">{{ composerForm.errors.captchaToken }}</p>
          <p v-else-if="composerForm.errors.images" class="composer-banner error">{{ composerForm.errors.images }}</p>
          <p v-else-if="composerForm.errors['images.0']" class="composer-banner error">{{ composerForm.errors['images.0'] }}</p>
          <p v-if="successMessage" class="composer-banner success">{{ successMessage }}</p>

          <button type="submit" class="composer-modal-submit" :disabled="composerForm.processing || !composerReady">
            {{ composerForm.processing ? 'Posting…' : 'Post anonymously' }}
          </button>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* ==========================================================================
   Wall — directory skin. Cards on a soft canvas, one accent green, hairline
   borders. Everything reads the --d-* tokens in app.css so dark mode is a
   palette swap rather than a second stylesheet.
   ========================================================================== */

.wall {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  /* The main column is narrower than the viewport, so the breakpoints below
     measure this element rather than the window. */
  container: wall / inline-size;
}

/* --- Shared furniture ----------------------------------------------------- */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0.62rem 1.1rem;
  border: 1px solid transparent;
  border-radius: var(--d-r-md);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 650;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s var(--b-ease);
}

.btn-green {
  background: var(--d-green);
  color: var(--d-green-ink);
}

.btn-green:hover {
  background: var(--d-green-hover);
}

.btn-plain {
  background: var(--d-card);
  border-color: var(--d-line);
  color: var(--d-ink);
}

.btn-plain:hover {
  border-color: var(--d-line-strong);
}

.btn-sm {
  padding: 0.5rem 0.85rem;
  font-size: 0.82rem;
}

.btn-block {
  width: 100%;
  margin-top: 0.9rem;
}

.link-green {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.84rem;
  font-weight: 650;
  color: var(--d-green-text);
  text-decoration: none;
  white-space: nowrap;
}

.link-green:hover {
  text-decoration: underline;
}

.card-kicker {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--d-muted);
}

.meta-line {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.meta-line b {
  color: var(--d-green-text);
  font-weight: 700;
}

.meta-line i {
  font-style: normal;
  color: var(--d-faint);
}

.tile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.3rem;
  height: 2.3rem;
  flex-shrink: 0;
  border-radius: var(--d-r-md);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.95rem;
  font-weight: 800;
  line-height: 1;
}

.tile-lg {
  width: 2.9rem;
  height: 2.9rem;
  font-size: 1.15rem;
  border-radius: var(--d-r-lg);
}

.tile-sm {
  width: 1.65rem;
  height: 1.65rem;
  font-size: 0.72rem;
  border-radius: var(--d-r-sm);
}

.empty {
  margin: 0;
  padding: 1.4rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  font-size: 0.88rem;
  color: var(--d-muted);
  text-align: center;
}

/* --- Hero ----------------------------------------------------------------- */

.hero {
  padding: 1.5rem 0 0.5rem;
}

.hero h1 {
  margin: 0;
  max-width: 20ch;
  font-size: 2.4rem;
  line-height: 1.1;
  font-weight: 800;
  letter-spacing: -0.035em;
  color: var(--d-ink);
}

.hero-lede {
  margin: 0.85rem 0 0;
  max-width: 46ch;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--d-muted);
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-top: 1.35rem;
}

.hero-proof {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--d-muted);
}

.proof-stack {
  display: inline-flex;
}

.proof-stack i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.7rem;
  height: 1.7rem;
  margin-left: -0.5rem;
  border-radius: 50%;
  border: 2px solid var(--d-canvas);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.66rem;
  font-style: normal;
  font-weight: 800;
}

.proof-stack i:first-child {
  margin-left: 0;
}

/* --- Post of the day ------------------------------------------------------ */

.spotlight {
  padding: 0.85rem 1.1rem 1.1rem;
  background: linear-gradient(135deg, var(--d-green-tint), var(--d-green-tint-2));
  border: 1px solid var(--d-green-tint-line);
  border-radius: var(--d-r-lg);
}

.spotlight-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.85rem;
}

.spotlight-flag,
.spotlight-date {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.spotlight-flag {
  color: var(--d-green-text);
}

.spotlight-date {
  color: var(--d-muted);
}

.spotlight-body {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.spotlight-text {
  flex: 1;
  min-width: 0;
}

.spotlight-text h2 {
  margin: 0.25rem 0 0.2rem;
  font-size: 1.15rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  line-height: 1.3;
  color: var(--d-ink);
  /* The whole post, line breaks and all: it is the one card on the page that
     exists to be read, so cutting it short defeats it. */
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.spotlight-sub {
  margin: 0;
  font-size: 0.78rem;
  color: var(--d-muted);
}

/* --- Vote pill ------------------------------------------------------------ */

/* Up arrow, score, down arrow. Stacked on the spotlight card where it reads
   as the reference's vote box, laid out in a row inside the feed's action
   bar where a column would stretch it. */
.vote {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 0.05rem;
  min-width: 2.6rem;
  padding: 0.2rem 0.3rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-card);
  color: var(--d-muted);
}

.vote-inline {
  flex-direction: row;
  gap: 0.15rem;
  min-width: 0;
  padding: 0.12rem 0.25rem;
  border-radius: var(--d-r-pill);
}

.vote b {
  min-width: 1.1rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--d-ink);
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.vote b.up {
  color: var(--d-green-text);
}

.vote b.down {
  color: #c53030;
}

.vote-arrow {
  display: inline-flex;
  padding: 0.15rem;
  border: 0;
  border-radius: 50%;
  background: none;
  color: var(--d-muted);
  cursor: pointer;
  transition: color 0.15s ease, background 0.15s ease;
}

.vote-arrow:hover {
  background: var(--d-soft);
  color: var(--d-ink);
}

/* Your own vote needs to read at a glance, and colour alone is thin at this
   size. The tint is mixed from currentColor so it follows the arrow in both
   themes; a browser without color-mix just keeps the colour change. */
.vote-arrow.on {
  color: var(--d-green-text);
  background: color-mix(in srgb, currentColor 16%, transparent);
}

.vote-arrow.on:last-child {
  color: #e05252;
}

.vote-arrow.on:hover {
  background: color-mix(in srgb, currentColor 26%, transparent);
}

.vote-static {
  padding: 0.35rem 0.4rem;
  background: transparent;
}

.vote-error {
  margin: 0;
  padding: 0.6rem 0.9rem;
  background: #fee2e2;
  border-radius: var(--d-r-md);
  font-size: 0.82rem;
  color: #991b1b;
}

/* --- Leaderboard ---------------------------------------------------------- */

.board {
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
  overflow: hidden;
}

.board-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 1rem 1.1rem;
}

.board-total {
  margin: 0.15rem 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--d-ink);
}

.board-tabs {
  display: inline-flex;
  gap: 0.15rem;
  padding: 0.2rem;
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
}

.board-tabs button {
  padding: 0.35rem 0.7rem;
  border: 0;
  border-radius: var(--d-r-pill);
  background: none;
  font: inherit;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--d-muted);
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s ease, color 0.15s ease;
}

.board-tabs button:hover {
  color: var(--d-ink);
}

.board-tabs button.active {
  background: var(--d-card);
  color: var(--d-ink);
  box-shadow: var(--d-shadow);
}

.board-list {
  margin: 0;
  padding: 0;
  list-style: none;
  border-top: 1px solid var(--d-line);
}

.board-list li {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.7rem 1.1rem 0.7rem 0.85rem;
  border-bottom: 1px solid var(--d-line);
}

.board-list li:last-child {
  border-bottom: 0;
}

.board-list li.lead {
  background: linear-gradient(90deg, var(--d-green-tint-2), transparent 55%);
}

.board-rank {
  width: 1rem;
  flex-shrink: 0;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--d-faint);
  text-align: center;
}

.board-text {
  flex: 1;
  min-width: 0;
}

.board-name {
  display: block;
  margin: 0.1rem 0 0.05rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.92rem;
  font-weight: 700;
  letter-spacing: -0.015em;
  color: var(--d-ink);
  text-decoration: none;
}

a.board-name:hover {
  color: var(--d-green-text);
  text-decoration: underline;
}

.board-name.as-text {
  cursor: default;
}

.board-sub {
  margin: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.78rem;
  color: var(--d-muted);
}

.board-sub .tally-up {
  color: var(--d-green);
}

.board-sub .tally-down {
  color: #f87171;
}

/* --- Feed ----------------------------------------------------------------- */

.feed-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1.4rem;
}

.feed-head h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--d-ink);
}

.feed-head p {
  margin: 0.3rem 0 0;
  font-size: 0.86rem;
  color: var(--d-muted);
}

.composer-trigger {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.7rem 0.75rem 0.7rem 0.7rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  box-shadow: var(--d-shadow);
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.composer-trigger:hover,
.composer-trigger:focus-visible {
  border-color: var(--d-green);
  box-shadow: var(--d-shadow-lift);
  outline: none;
}

.composer-avatar {
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.composer-trigger-text {
  flex: 1;
  min-width: 0;
  font-size: 0.88rem;
  color: var(--d-muted);
}

.composer-trigger-photo {
  display: inline-flex;
  color: var(--d-muted);
}

.composer-post {
  padding: 0.4rem 0.9rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.8rem;
  font-weight: 700;
}

.feed-grid {
  display: grid;
  grid-template-columns: repeat(var(--cols, 2), minmax(0, 1fr));
  align-items: start;
  gap: 0.85rem;
}

.feed-col {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  min-width: 0;
}

.post {
  display: flex;
  flex-direction: column;
  padding: 0.9rem 1rem 0.65rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s var(--b-ease);
}

.post:hover {
  border-color: var(--d-line-strong);
  box-shadow: var(--d-shadow-lift);
  transform: translateY(-2px);
}

.post.is-featured {
  border-color: var(--d-green);
}

.post-head {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin-bottom: 0.6rem;
}

.post-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: var(--d-r-sm);
  object-fit: cover;
  flex-shrink: 0;
}

.post-head-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.post-name {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--d-ink);
}

.post-verified {
  color: var(--d-green);
  flex-shrink: 0;
}

.post-meta {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.73rem;
  color: var(--d-muted);
}

.post-meta i {
  font-style: normal;
  color: var(--d-faint);
}

.post-tag {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 0.73rem;
  font-weight: 650;
  color: var(--d-green-text);
  cursor: pointer;
}

.post-tag:hover {
  text-decoration: underline;
}

.post-body {
  flex: 1;
}

.post-content {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--d-ink-2);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.post-content.clamped {
  display: -webkit-box;
  -webkit-line-clamp: 6;
  line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.post-seemore {
  margin-top: 0.2rem;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-green-text);
  cursor: pointer;
}

.post-seemore:hover {
  text-decoration: underline;
}

.photo-grid {
  display: grid;
  gap: 3px;
  margin-top: 0.7rem;
  border-radius: var(--d-r-md);
  overflow: hidden;
}

.photo-grid.tiles-1 {
  grid-template-columns: 1fr;
}

.photo-grid.tiles-2 {
  grid-template-columns: 1fr 1fr;
}

.photo-grid.tiles-3 {
  grid-template-columns: 1fr 1fr;
}

.photo-grid.tiles-3 .photo-tile:first-child {
  grid-column: span 2;
}

.photo-grid.tiles-4 {
  grid-template-columns: 1fr 1fr;
}

.photo-tile {
  position: relative;
  display: block;
  padding: 0;
  border: 0;
  background: var(--d-soft);
  aspect-ratio: 4 / 3;
  overflow: hidden;
  cursor: zoom-in;
}

.photo-grid.tiles-1 .photo-tile {
  aspect-ratio: 16 / 10;
}

.photo-tile img,
.photo-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.3s var(--b-ease);
}

.photo-tile:hover img {
  transform: scale(1.03);
}

.photo-play,
.photo-more {
  position: absolute;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.photo-play {
  inset: 0;
  margin: auto;
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.55);
}

.photo-more {
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  font-size: 1.1rem;
  font-weight: 700;
}

.post-actions {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin-top: 0.7rem;
  padding-top: 0.45rem;
  border-top: 1px solid var(--d-line);
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.35rem 0.5rem;
  border: 0;
  border-radius: var(--d-r-sm);
  background: none;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--d-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.action:hover {
  background: var(--d-soft);
  color: var(--d-ink);
}

.action.active {
  color: var(--d-green-text);
}

/* --- Rail ----------------------------------------------------------------- */

.rail-stat {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  margin: 0 0 0.1rem;
  padding: 0 0.2rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.rail-stat strong {
  font-size: 0.9rem;
  letter-spacing: -0.01em;
  color: var(--d-ink);
}

.rail-stat i {
  font-style: normal;
  letter-spacing: 0.02em;
  text-transform: none;
  font-weight: 600;
}

.rail-card {
  padding: 0.9rem 0.95rem 1rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

/* Inside a rail card the shared empty state would read as a card in a card. */
.rail-card .empty {
  padding: 0.3rem 0;
  border: 0;
  background: none;
  font-size: 0.8rem;
  text-align: left;
}

.rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.rail-head h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-all {
  padding: 0;
  text-decoration: none;
  border: 0;
  background: none;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-green-text);
  cursor: pointer;
}

.rail-all.muted {
  color: var(--d-faint);
  cursor: default;
}

.tag-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.3rem;
}

.tag-cell {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.4rem;
  border: 1px solid transparent;
  border-radius: var(--d-r-md);
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.tag-cell:hover {
  background: var(--d-soft);
}

.tag-cell.active {
  border-color: var(--d-green);
  background: var(--d-green-tint);
}

.tag-cell-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.tag-cell-text b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-ink);
}

.tag-cell-text small {
  font-size: 0.68rem;
  color: var(--d-muted);
}

.rail-apps {
  margin: 0;
  padding: 0;
  list-style: none;
}

.rail-apps a {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem;
  border-radius: var(--d-r-md);
  text-decoration: none;
  transition: background 0.15s ease;
}

.rail-apps a:hover {
  background: var(--d-soft);
}

.rail-apps img {
  width: 1.65rem;
  height: 1.65rem;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  object-fit: cover;
  background: var(--d-soft);
}

.rail-app-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.rail-app-text b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--d-ink);
}

.rail-app-text small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.68rem;
  color: var(--d-muted);
}

.rail-stats {
  margin: 0;
  padding: 0;
  list-style: none;
}

.rail-stats li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.38rem 0;
  border-bottom: 1px dashed var(--d-line);
  font-size: 0.82rem;
  color: var(--d-muted);
}

.rail-stats li:last-child {
  border-bottom: 0;
}

.rail-stats strong {
  font-weight: 700;
  color: var(--d-ink);
}

.rail-promo h2 {
  margin: 0 0 0.6rem;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-rules {
  margin: 0;
  padding-left: 1.05rem;
  font-size: 0.8rem;
  line-height: 1.5;
  color: var(--d-muted);
}

.rail-rules li {
  margin-bottom: 0.4rem;
}

.rail-rules b {
  color: var(--d-ink);
  font-weight: 650;
}

/* --- Lightbox ------------------------------------------------------------- */

.lightbox {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem;
  background: rgba(0, 0, 0, 0.88);
}

.lightbox-image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  border-radius: var(--d-r-md);
}

.lightbox-close,
.lightbox-nav {
  position: absolute;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.4rem;
  height: 2.4rem;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  cursor: pointer;
  transition: background 0.15s ease;
}

.lightbox-close:hover,
.lightbox-nav:hover {
  background: rgba(255, 255, 255, 0.18);
}

.lightbox-close {
  top: 1.1rem;
  right: 1.1rem;
}

.lightbox-nav.prev {
  left: 1.1rem;
}

.lightbox-nav.next {
  right: 1.1rem;
}

.lightbox-counter {
  position: absolute;
  bottom: 1.2rem;
  left: 50%;
  transform: translateX(-50%);
  padding: 0.3rem 0.75rem;
  border-radius: var(--d-r-pill);
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
  font-size: 0.78rem;
}

/* --- Composer modal ------------------------------------------------------- */

.composer-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: var(--d-scrim);
  overflow-y: auto;
}

.composer-modal {
  width: min(34rem, 100%);
  max-height: 90vh;
  overflow-y: auto;
  padding: 1.1rem 1.2rem 1.3rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-xl);
  box-shadow: var(--d-shadow-lift);
  animation: modal-in 0.2s var(--b-ease);
}

@keyframes modal-in {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
}

.composer-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.8rem;
  border-bottom: 1px solid var(--d-line);
}

.composer-modal-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  color: var(--d-ink);
}

.composer-modal-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border: 1px solid var(--d-line);
  border-radius: 50%;
  background: var(--d-card);
  color: var(--d-muted);
  cursor: pointer;
}

.composer-modal-close:hover {
  color: var(--d-ink);
  border-color: var(--d-line-strong);
}

.composer-modal-user {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.9rem 0;
}

.composer-modal-user-text {
  display: flex;
  flex-direction: column;
}

.composer-modal-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--d-ink);
}

.composer-modal-sub {
  font-size: 0.75rem;
  color: var(--d-muted);
}

.composer-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.composer-modal-textarea {
  width: 100%;
  padding: 0.8rem 0.9rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  font: inherit;
  font-size: 0.92rem;
  line-height: 1.55;
  color: var(--d-ink);
  resize: vertical;
}

.composer-modal-textarea:focus {
  outline: none;
  border-color: var(--d-green);
  background: var(--d-card);
}

.composer-modal-textarea::placeholder {
  color: var(--d-faint);
}

.composer-tags-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0.5rem;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-ink);
}

.composer-tags-required {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.composer-tag-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.composer-tag {
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--d-muted);
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.composer-tag:hover {
  border-color: var(--d-green);
  color: var(--d-green-text);
}

.composer-tag.active {
  background: var(--d-green);
  border-color: var(--d-green);
  color: var(--d-green-ink);
}

.composer-custom-tag {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  margin-top: 0.5rem;
  padding: 0.4rem 0.7rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-soft);
}

.composer-custom-tag-hash {
  font-weight: 700;
  color: var(--d-green-text);
}

.composer-custom-tag-input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 0.82rem;
  color: var(--d-ink);
}

.composer-tags-hint,
.composer-tags-error {
  margin: 0.45rem 0 0;
  font-size: 0.76rem;
}

.composer-tags-hint {
  color: var(--d-muted);
}

.composer-tags-error {
  color: #dc2626;
}

.composer-attach-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.6rem 0.8rem;
  border: 1px dashed var(--d-line-strong);
  border-radius: var(--d-r-md);
  font-size: 0.82rem;
  color: var(--d-muted);
  cursor: pointer;
}

.composer-attach-row:hover {
  border-color: var(--d-green);
  color: var(--d-green-text);
}

.composer-attach-icon {
  position: relative;
  display: inline-flex;
}

.composer-attach-icon input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.composer-file-list {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.78rem;
  color: var(--d-muted);
}

.composer-attach-hint {
  margin: 0;
  font-size: 0.74rem;
  color: var(--d-faint);
}

.composer-banner {
  margin: 0;
  padding: 0.55rem 0.75rem;
  border-radius: var(--d-r-sm);
  font-size: 0.8rem;
}

.composer-banner.error {
  background: #fee2e2;
  color: #991b1b;
}

.composer-banner.success {
  background: var(--d-green-tint);
  color: var(--d-green-text);
}

/* Quiet on purpose. Someone who just wrote a hard post does not need a red
   alarm box; they need a number they can read without feeling caught. */
.support-note {
  margin-bottom: 1.25rem;
  padding: 1rem 1.15rem;
  border: 1px solid var(--d-line);
  border-left: 3px solid var(--d-green);
  border-radius: var(--d-r-md);
  background: var(--d-card);
}

.support-lede {
  margin: 0 0 0.6rem;
  font-size: 0.92rem;
  color: var(--d-ink);
}

.support-note ul {
  margin: 0;
  padding-left: 1.1rem;
  display: grid;
  gap: 0.3rem;
  font-size: 0.86rem;
  color: var(--d-muted);
}

.composer-modal-submit {
  padding: 0.7rem 1rem;
  border: 0;
  border-radius: var(--d-r-md);
  background: var(--d-green);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--d-green-ink);
  cursor: pointer;
  transition: background 0.15s ease;
}

.composer-modal-submit:hover:not(:disabled) {
  background: var(--d-green-hover);
}

.composer-modal-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* --- Motion --------------------------------------------------------------- */

.rise {
  animation: rise 0.5s var(--b-ease) backwards;
  animation-delay: calc(var(--i, 0) * 60ms);
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

/* --- Responsive ----------------------------------------------------------- */

@container wall (max-width: 720px) {
  .hero h1 {
    font-size: 2rem;
  }
}

@container wall (max-width: 560px) {
  .hero h1 {
    font-size: 1.7rem;
  }

  .spotlight-body {
    flex-wrap: wrap;
  }

  .spotlight-text {
    flex-basis: 60%;
  }

  .board-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .board-posted,
  .board-text .meta-line i,
  .board-text .meta-line span {
    display: none;
  }

  .feed-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }

  .action span {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .rise,
  .composer-modal {
    animation: none;
  }

  .post:hover {
    transform: none;
  }

  .photo-tile:hover img {
    transform: none;
  }
}
</style>
