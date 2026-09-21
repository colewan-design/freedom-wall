<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import NewsfeedLayout from '../Layouts/NewsfeedLayout.vue';
import TurnstileWidget from '../Components/TurnstileWidget.vue';
import { timeAgo } from '../lib/date';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  posts: { type: Array, required: true },
  // Hashtag list comes from Submission::CATEGORIES so the chips can never drift
  // from what the form request accepts.
  categories: { type: Array, default: () => [] },
  // Totals for the whole wall, counted in SQL. Deriving them from `posts` would
  // only ever describe the most recent 50 rows the page actually renders.
  stats: {
    type: Object,
    default: () => ({ total: 0, withPhotos: 0, textOnly: 0 }),
  },
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.success ?? null);
const composerTextarea = ref(null);
const fileInput = ref(null);
const composerModalOpen = ref(false);

const totalPosts = computed(() => props.stats.total ?? 0);
const withPhotos = computed(() => props.stats.withPhotos ?? 0);
const textOnly = computed(() => props.stats.textOnly ?? 0);

const composerForm = useForm({
  content: '',
  category: '',
  images: [],
  captchaToken: null,
});

const composerReady = computed(
  () => composerForm.content.trim().length > 0 && composerForm.category !== '',
);

function onComposerFileChange(e) {
  composerForm.images = Array.from(e.target.files || []);
}

function selectCategory(category) {
  composerForm.category = category;
  composerForm.clearErrors('category');
}

function onComposerSubmit() {
  if (!composerForm.content.trim()) {
    composerForm.setError('content', 'Please write something before submitting.');
    return;
  }
  if (!composerForm.category) {
    composerForm.setError('category', 'Please pick a hashtag for your post.');
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

function focusComposer() {
  openComposerModal();
}

onMounted(() => {
  if (window.location.hash === '#composer') {
    openComposerModal();
  }
});

const search = inject('wallSearch', ref(''));

const filteredPosts = computed(() => {
  const query = search.value.trim().toLowerCase();
  if (!query) return props.posts;
  return props.posts.filter((post) => post.content?.toLowerCase().includes(query));
});

const reactions = reactive({});

function reactionFor(post) {
  if (!reactions[post.id]) {
    reactions[post.id] = { liked: false, saved: false };
  }
  return reactions[post.id];
}

function toggleLike(post) {
  reactionFor(post).liked = !reactionFor(post).liked;
}

function toggleSave(post) {
  reactionFor(post).saved = !reactionFor(post).saved;
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
  <div class="wall-page">
    <header class="masthead rise" :style="riseDelay(0)">
      <span class="halftone" aria-hidden="true"></span>
      <p class="section-label">01 — bsu community</p>
      <h1>freedom wall</h1>
      <p class="lede">
        Confessions, rants, and stories from the BSU community. Written anonymously,
        reviewed by a human, then posted for everyone to read.
      </p>
      <div class="masthead-actions">
        <button type="button" class="btn-invert" @click="focusComposer">Start a discussion</button>
        <Link href="/id-check" class="text-link">try id check <span aria-hidden="true">→</span></Link>
      </div>
    </header>

    <!-- Hairline dividers are the design here — no gaps, no fills. -->
    <dl class="stat-row rise" :style="riseDelay(1)">
      <div class="stat">
        <dt>posts</dt>
        <dd>{{ totalPosts }}</dd>
      </div>
      <div class="stat">
        <dt>with photos</dt>
        <dd>{{ withPhotos }}</dd>
      </div>
      <div class="stat">
        <dt>text only</dt>
        <dd>{{ textOnly }}</dd>
      </div>
    </dl>

    <button id="composer" type="button" class="composer-trigger rise" :style="riseDelay(2)" @click="openComposerModal">
      <img src="/images/branding/bsufw-mark-64.png" alt="" class="composer-avatar" />
      <span class="composer-trigger-text">What's on your mind?</span>
      <span class="composer-trigger-photo" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.4" />
          <circle cx="8.5" cy="10" r="1.6" fill="currentColor" />
          <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
    </button>

    <div class="feed-header rise" :style="riseDelay(3)">
      <h2 class="section-label">02 — latest</h2>
      <button type="button" class="text-link" @click="focusComposer">
        new post <span aria-hidden="true">→</span>
      </button>
    </div>

    <p v-if="filteredPosts.length === 0" class="hint">
      {{ posts.length === 0 ? 'No approved posts yet.' : 'No posts match your search.' }}
    </p>

    <div v-else class="feed-list">
      <article
        v-for="(post, index) in filteredPosts"
        :id="`post-${post.id}`"
        :key="post.id"
        class="feed-card rise"
        :style="riseDelay(index + 4)"
      >
        <div class="feed-post-header">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="feed-avatar" />
          <div class="feed-header-text">
            <div class="feed-name-row">
              <span class="feed-name">BSU Freedom Wall</span>
              <svg class="feed-verified" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true" title="Official page">
                <path
                  d="M9.5 12.5 11 14l4-4.5M12 3.5l1.9 1.02 2.15-.35 1.06 1.9 1.9 1.06-.35 2.15L20.5 11l-1.02 1.9.35 2.15-1.9 1.06-1.06 1.9-2.15-.35L12 20.5l-1.9-1.02-2.15.35-1.06-1.9-1.9-1.06.35-2.15L3.5 12l1.02-1.9-.35-2.15 1.9-1.06 1.06-1.9 2.15.35L12 3.5Z"
                  fill="currentColor"
                />
                <path d="m9.2 12.4 1.8 1.8 3.8-4.2" stroke="var(--b-bg)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="feed-sub-meta">
              <span class="timestamp">{{ timeAgo(post.reviewed_at) }}</span>
              <span class="dot" aria-hidden="true">/</span>
              <span>public</span>
            </div>
          </div>
        </div>

        <div class="feed-content-wrap">
          <p
            :ref="registerContent(post.id)"
            class="feed-content"
            :class="{ clamped: !expandedPosts[post.id] }"
          >{{ post.content }}</p>
          <button
            v-if="overflowingPosts[post.id] || expandedPosts[post.id]"
            type="button"
            class="feed-seemore"
            @click="toggleExpand(post)"
          >
            {{ expandedPosts[post.id] ? 'see less' : 'see more' }}
          </button>
        </div>

        <div v-if="post.image_urls?.length" class="feed-photo-grid" :class="`tiles-${Math.min(post.image_urls.length, 4)}`">
          <button
            v-for="(imageUrl, imageIndex) in post.image_urls.slice(0, 4)"
            :key="imageIndex"
            type="button"
            class="feed-photo-tile"
            @click="openLightbox(post, imageIndex)"
          >
            <img :src="imageUrl" alt="" />
            <span v-if="imageIndex === 3 && post.image_urls.length > 4" class="feed-photo-more">
              +{{ post.image_urls.length - 4 }}
            </span>
          </button>
        </div>

        <div class="feed-actions">
          <button type="button" class="action" :class="{ active: reactionFor(post).liked }" @click="toggleLike(post)">
            <svg width="14" height="14" viewBox="0 0 24 24" :fill="reactionFor(post).liked ? 'currentColor' : 'none'" aria-hidden="true">
              <path
                d="M7 10v11H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h3Zm0 0 4.5-8a2 2 0 0 1 2.24.6c.5.6.7 1.4.48 2.16L13 10h5.2a2 2 0 0 1 1.96 2.4l-1.5 7A2 2 0 0 1 16.7 21H10a3 3 0 0 1-3-3"
                stroke="currentColor"
                stroke-width="1.4"
                stroke-linejoin="round"
              />
            </svg>
            Like
          </button>
          <button type="button" class="action" @click="sharePost(post)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="18" cy="5" r="2.4" stroke="currentColor" stroke-width="1.4" />
              <circle cx="6" cy="12" r="2.4" stroke="currentColor" stroke-width="1.4" />
              <circle cx="18" cy="19" r="2.4" stroke="currentColor" stroke-width="1.4" />
              <path d="m8.1 10.8 7.8-4.6M8.1 13.2l7.8 4.6" stroke="currentColor" stroke-width="1.4" />
            </svg>
            Share
          </button>
          <button type="button" class="action" :class="{ active: reactionFor(post).saved }" @click="toggleSave(post)">
            <svg width="14" height="14" viewBox="0 0 24 24" :fill="reactionFor(post).saved ? 'currentColor' : 'none'" aria-hidden="true">
              <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
            </svg>
            Save
          </button>
        </div>
      </article>
    </div>

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

        <img :src="lightbox.images[lightbox.index]" alt="" class="lightbox-image" />

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
            <h3>create post</h3>
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
              <span class="composer-modal-sub">anonymous / reviewed before posting</span>
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
                pick a hashtag <span class="composer-tags-required">required</span>
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
              </div>
              <p v-if="composerForm.category" class="composer-tags-hint">
                #{{ composerForm.category }} will be added to the end of your post.
              </p>
            </div>

            <label class="composer-attach-row">
              <span>
                {{ composerForm.images.length ? `${composerForm.images.length} image${composerForm.images.length > 1 ? 's' : ''} selected` : 'Add to your post' }}
              </span>
              <span class="composer-attach-icon">
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
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
  </div>
</template>

<style scoped>
/*
 * bryl-minimal: monochrome, typography-driven. No accent colour anywhere —
 * emphasis comes from inversion (ink fill on background), from switching to
 * the mono register, and from the halftone dot field. Palette tokens (--b-*)
 * are supplied by NewsfeedLayout under .nf-shell.bryl.
 */

.wall-page {
  max-width: 42rem;
  margin: 0 auto;
  font-family: var(--b-sans);
  color: var(--b-ink);
}

/* --- shared type registers ---------------------------------------------- */

/* The signature micro-label: numbered, em-dashed, display font, gray 400. */
.section-label {
  margin: 0;
  font-family: var(--b-display);
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.text-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  border: none;
  background: none;
  padding: 0;
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
  text-decoration: none;
  cursor: pointer;
  transition: color 0.2s ease;
}

.text-link span {
  display: inline-block;
  transition: transform 0.2s var(--b-ease);
}

.text-link:hover {
  color: var(--b-ink);
}

.text-link:hover span {
  transform: translate(2px, -2px);
}

.btn-invert {
  border: none;
  border-radius: var(--b-r-input);
  padding: 0.6rem 1rem;
  background: var(--b-green);
  color: var(--b-green-ink);
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.2s ease;
}

.btn-invert:hover {
  background: var(--b-green-hover);
}

.btn-invert:disabled {
  background: var(--b-300);
  cursor: not-allowed;
}

/* --- masthead ------------------------------------------------------------ */

.masthead {
  position: relative;
  overflow: hidden;
  padding: 1rem 0 2.25rem;
  border-bottom: 1px solid var(--b-200);
}

/* Print-style halftone: 1px dots on a 9px cell, masked so the field
   dissolves instead of ending on a hard edge. One accent per page. */
.halftone {
  position: absolute;
  top: 0;
  right: 0;
  width: 240px;
  height: 190px;
  background-image: radial-gradient(var(--b-dot) 1px, transparent 1px);
  background-size: 9px 9px;
  /* Explicit radius: `closest-side` measured from a corner collapses to 0
     and would hide the field entirely. */
  -webkit-mask-image: radial-gradient(circle 230px at 100% 0%, #000 0%, transparent 100%);
  mask-image: radial-gradient(circle 230px at 100% 0%, #000 0%, transparent 100%);
  pointer-events: none;
}

.masthead h1 {
  position: relative;
  margin: 0.85rem 0 0;
  font-family: var(--b-display);
  font-size: clamp(2rem, 9vw, 3rem);
  font-weight: 400;
  line-height: 1;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.lede {
  position: relative;
  margin: 1rem 0 0;
  max-width: 42ch;
  font-size: 15px;
  line-height: 1.6;
  color: var(--b-500);
}

.masthead-actions {
  position: relative;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1.25rem;
  margin-top: 1.75rem;
}

/* --- stat row ------------------------------------------------------------ */

.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  margin: 0 0 3.5rem;
  border-bottom: 1px solid var(--b-200);
}

.stat {
  padding: 1.1rem 1rem;
  border-right: 1px solid var(--b-200);
}

.stat:first-child {
  padding-left: 0;
}

.stat:last-child {
  border-right: none;
}

.stat dt {
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
}

.stat dd {
  margin: 0.4rem 0 0;
  font-family: var(--b-display);
  font-size: 1.5rem;
  line-height: 1;
  color: var(--b-ink);
}

/* --- composer trigger ---------------------------------------------------- */

.composer-trigger {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  background: var(--b-bg);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-md);
  padding: 0.75rem;
  margin-bottom: 3.5rem;
  scroll-margin-top: 5rem;
  cursor: pointer;
  text-align: left;
  box-shadow: var(--b-shadow);
  transition: border-color 0.2s ease, box-shadow 0.35s var(--b-ease);
}

.composer-trigger:hover {
  border-color: var(--b-300);
  box-shadow: var(--b-shadow-hover);
}

/* Brand chrome is desaturated to hold the monochrome rule; user-submitted
   photos below are left exactly as they were sent. */
.composer-avatar {
  width: 34px;
  height: 34px;
  border-radius: var(--b-r-thumb);
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--b-200);
  filter: grayscale(1);
}

.composer-trigger-text {
  flex: 1;
  min-width: 0;
  padding: 0.5rem 0.75rem;
  border-radius: var(--b-r-pill);
  background: var(--b-50);
  border: 1px solid var(--b-200);
  color: var(--b-400);
  font-family: var(--b-mono);
  font-size: 12px;
}

.composer-trigger-photo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  color: var(--b-400);
  flex-shrink: 0;
}

/* --- feed ---------------------------------------------------------------- */

.feed-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-bottom: 0.75rem;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid var(--b-200);
}

.hint {
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
}

.feed-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.feed-card {
  display: flex;
  flex-direction: column;
  background: var(--b-bg);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-card);
  padding: 1.25rem;
  box-shadow: var(--b-shadow);
  transition: box-shadow 0.35s var(--b-ease), transform 0.42s var(--b-ease), border-color 0.2s ease;
}

.feed-card:hover {
  border-color: var(--b-green);
  box-shadow: var(--b-shadow-hover);
}

.feed-post-header {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  margin-bottom: 0.85rem;
}

.feed-avatar {
  width: 34px;
  height: 34px;
  border-radius: var(--b-r-thumb);
  object-fit: cover;
  flex-shrink: 0;
  border: 1px solid var(--b-200);
  filter: grayscale(1);
}

.feed-header-text {
  flex: 1;
  min-width: 0;
}

.feed-name-row {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.feed-name {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--b-ink);
}

.feed-verified {
  color: var(--b-green-text);
  flex-shrink: 0;
}

.feed-sub-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.15rem;
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
}

.feed-content-wrap {
  margin-bottom: 0.85rem;
}

.feed-content {
  margin: 0;
  white-space: pre-wrap;
  font-size: 15px;
  line-height: 1.65;
  color: var(--b-ink);
}

.feed-content.clamped {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.feed-seemore {
  display: inline-flex;
  border: none;
  background: none;
  padding: 0.5rem 0 0;
  margin: 0;
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
  cursor: pointer;
  transition: color 0.2s ease;
}

.feed-seemore:hover {
  color: var(--b-ink);
}

.feed-photo-grid {
  display: grid;
  gap: 2px;
  border-radius: var(--b-r-md);
  overflow: hidden;
  margin-bottom: 1rem;
  border: 1px solid var(--b-200);
}

.feed-photo-grid.tiles-1 {
  grid-template-columns: 1fr;
}

.feed-photo-grid.tiles-1 .feed-photo-tile {
  aspect-ratio: 16 / 10;
}

.feed-photo-grid.tiles-2 {
  grid-template-columns: 1fr 1fr;
  height: 240px;
}

.feed-photo-grid.tiles-3 {
  grid-template-columns: 1.2fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.feed-photo-grid.tiles-3 .feed-photo-tile:first-child {
  grid-row: 1 / 3;
}

.feed-photo-grid.tiles-4 {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.feed-photo-tile {
  position: relative;
  overflow: hidden;
  background: var(--b-50);
  border: none;
  padding: 0;
  margin: 0;
  cursor: pointer;
  display: block;
  width: 100%;
  height: 100%;
}

.feed-photo-tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.5s var(--b-ease);
}

.feed-photo-tile:hover img {
  transform: scale(1.04);
}

.feed-photo-more {
  position: absolute;
  inset: 0;
  background: rgba(10, 10, 10, 0.6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--b-display);
  font-size: 1.25rem;
}

.feed-actions {
  display: flex;
  gap: 1.5rem;
  border-top: 1px solid var(--b-200);
  padding-top: 0.85rem;
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: none;
  padding: 0;
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
  cursor: pointer;
  transition: color 0.2s ease;
}

.action:hover {
  color: var(--b-ink);
}

.action.active {
  color: var(--b-green-text);
}

/* --- entrance ------------------------------------------------------------ */

.rise {
  opacity: 0;
  animation: rise 0.7s var(--b-ease) forwards;
  animation-delay: calc(50ms + var(--i, 0) * 70ms);
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

/* --- lightbox ------------------------------------------------------------ */

.lightbox {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(10, 10, 10, 0.92);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-image {
  max-width: 90vw;
  max-height: 88vh;
  object-fit: contain;
  border-radius: var(--b-r-sm);
}

.lightbox-close,
.lightbox-nav {
  position: fixed;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  color: #fff;
  border-radius: var(--b-r-pill);
  cursor: pointer;
  transition: background 0.2s ease;
}

.lightbox-close:hover,
.lightbox-nav:hover {
  background: rgba(255, 255, 255, 0.2);
}

.lightbox-close {
  top: 1.25rem;
  left: 1.25rem;
  width: 2.4rem;
  height: 2.4rem;
}

.lightbox-nav {
  top: 50%;
  transform: translateY(-50%);
  width: 2.8rem;
  height: 2.8rem;
}

.lightbox-nav.prev {
  left: 1.25rem;
}

.lightbox-nav.next {
  right: 1.25rem;
}

.lightbox-counter {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.18);
  color: #fff;
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 1px;
  padding: 0.35rem 0.9rem;
  border-radius: var(--b-r-pill);
}

/* --- composer modal ------------------------------------------------------ */

.composer-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: var(--b-scrim, rgba(10, 10, 10, 0.3));
  backdrop-filter: blur(10px);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 6vh 1rem 2rem;
  overflow-y: auto;
}

.composer-modal {
  width: 100%;
  max-width: 30rem;
  background: var(--b-bg);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-card);
  padding: 1.75rem;
  box-shadow: var(--b-shadow-modal);
  animation: modal-in 0.2s var(--b-ease);
}

@keyframes modal-in {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.composer-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--b-200);
  margin-bottom: 1.25rem;
}

.composer-modal-header h3 {
  margin: 0;
  font-family: var(--b-display);
  font-size: 13px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.composer-modal-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  border-radius: var(--b-r-pill);
  border: 1px solid var(--b-200);
  background: var(--b-bg);
  color: var(--b-500);
  cursor: pointer;
  transition: color 0.2s ease, border-color 0.2s ease;
}

.composer-modal-close:hover {
  color: var(--b-ink);
  border-color: var(--b-300);
}

.composer-modal-user {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin-bottom: 1rem;
}

.composer-modal-user-text {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.composer-modal-name {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--b-ink);
}

.composer-modal-sub {
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
}

.composer-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Invisible input inside a composed widget — no border, no focus ring. */
.composer-modal-textarea {
  width: 100%;
  min-height: 7rem;
  padding: 0;
  border: none;
  background: transparent;
  font-family: var(--b-sans);
  font-size: 17px;
  line-height: 1.6;
  color: var(--b-ink);
  resize: vertical;
}

.composer-modal-textarea:focus {
  outline: none;
}

.composer-modal-textarea::placeholder {
  color: var(--b-400);
}

/* Required hashtag picker. Selection reads as inversion, same as .btn-invert. */
.composer-tags {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--b-200);
}

.composer-tags-label {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
}

.composer-tags-required {
  font-size: 9px;
  letter-spacing: 1px;
  color: var(--b-400);
}

.composer-tags-required::before {
  content: '/ ';
}

.composer-tag-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.composer-tag {
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--b-200);
  border-radius: 999px;
  background: var(--b-bg);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.5px;
  color: var(--b-500);
  cursor: pointer;
  transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
}

.composer-tag:hover {
  border-color: var(--b-400);
  color: var(--b-ink);
}

.composer-tag.active {
  background: var(--b-ink);
  border-color: var(--b-ink);
  color: var(--b-bg);
}

.composer-tags-hint {
  margin: 0;
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.5px;
  color: var(--b-400);
}

.composer-attach-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.55rem 0.75rem;
  background: var(--b-50);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-input);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
  cursor: pointer;
  transition: border-color 0.2s ease, color 0.2s ease;
}

.composer-attach-row:hover {
  border-color: var(--b-300);
  color: var(--b-ink);
}

.composer-attach-icon {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.8rem;
  height: 1.8rem;
  color: var(--b-400);
  flex-shrink: 0;
}

.composer-attach-icon input[type='file'] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.composer-file-list {
  margin: -0.5rem 0 0;
  padding-left: 1.1rem;
  font-family: var(--b-mono);
  font-size: 11px;
  color: var(--b-500);
}

.composer-banner {
  margin: 0;
  padding: 0.65rem 0.85rem;
  border-radius: var(--b-r-sm);
  border: 1px solid var(--b-200);
  background: var(--b-50);
  font-size: 13px;
  line-height: 1.5;
  color: var(--b-ink);
}

/* No red or green — errors read as ink on the ramp, marked by a mono prefix. */
.composer-banner::before {
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  color: var(--b-400);
  display: block;
  margin-bottom: 0.2rem;
}

.composer-banner.error::before {
  content: 'ERROR';
}

.composer-banner.success::before {
  content: 'SENT';
}

.composer-banner.success {
  border-color: var(--b-ink);
}

.composer-modal-submit {
  width: 100%;
  border: none;
  border-radius: var(--b-r-input);
  padding: 0.75rem 1rem;
  background: var(--b-green);
  color: var(--b-green-ink);
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.2s ease;
}

.composer-modal-submit:hover:not(:disabled) {
  background: var(--b-green-hover);
}

.composer-modal-submit:disabled {
  background: var(--b-300);
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .composer-trigger .composer-avatar {
    display: none;
  }

  .composer-modal {
    padding: 1.25rem;
  }

  .lightbox-nav {
    width: 2.4rem;
    height: 2.4rem;
  }

  .stat {
    padding: 0.9rem 0.6rem;
  }
}

/* The design has to be complete when perfectly still. */
@media (prefers-reduced-motion: reduce) {
  .rise {
    opacity: 1;
    animation: none;
  }

  .composer-modal {
    animation: none;
  }

  .feed-card:hover {
    transform: none;
  }

  .feed-photo-tile:hover img,
  .text-link:hover span {
    transform: none;
  }
}
</style>
