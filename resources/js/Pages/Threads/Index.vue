<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, provide, ref, watch } from 'vue';
import ThreadReplyNode from '../../Components/ThreadReplyNode.vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';
import { formatDateTime, timeAgo } from '../../lib/date';
import { hashtagError, normalizeHashtag } from '../../lib/hashtag';
import { REPLY_SORTS, countReplies, findReply, insertReply, sortReplies } from '../../lib/threads';

// Rendered here rather than through defineOptions: the thread list, the topic
// filter and the trending panel live in the shell's #rail slot, which Inertia's
// persistent-layout option cannot reach.

const props = defineProps({
  thread: { type: Object, default: null },
  threads: { type: Array, default: () => [] },
  trending: { type: Array, default: () => [] },
  topics: { type: Array, default: () => [] },
  activeTags: { type: Array, default: () => [] },
  reportReasons: { type: Array, default: () => [] },
  threadStats: { type: Object, default: () => ({ totalThreads: 0, totalReplies: 0 }) },
});

const MAX_TITLE = 160;
const MAX_BODY = 4000;
const MAX_REPLY = 2000;

const GUIDELINES = [
  'Stay respectful and open-minded.',
  'No personal attacks or harassment.',
  'Keep it anonymous and on-topic.',
];

const REASON_LABELS = {
  harassment: 'Harassment or bullying',
  'personal-info': 'Shares personal info',
  spam: 'Spam or advertising',
  hate: 'Hate speech',
  other: 'Something else',
};

const replies = ref(props.thread?.replies ?? []);
const replyCount = ref(props.thread?.reply_count ?? 0);
const sort = ref('oldest');

const composerOpen = ref(false);
const replyingTo = ref(null);
const replyDraft = ref('');
const rootDraft = ref('');
const replyError = ref('');
const posting = ref(false);
const reportedReplies = ref([]);
const threadReported = ref(false);
const threadMenuOpen = ref(false);
const notice = ref('');

// Inertia keeps this component alive between threads, so every piece of local
// state has to be rebuilt when a different thread is opened.
watch(
  () => props.thread?.id,
  () => {
    replies.value = props.thread?.replies ?? [];
    replyCount.value = props.thread?.reply_count ?? 0;
    replyingTo.value = null;
    replyDraft.value = '';
    rootDraft.value = '';
    replyError.value = '';
    reportedReplies.value = [];
    threadReported.value = false;
    threadMenuOpen.value = false;
    notice.value = '';
  },
);

// --- Keeping threads ------------------------------------------------------
// Save and Follow are personal bookmarks with no account behind them, so they
// stay on the device rather than pretending to be server-side subscriptions.

const savedThreads = ref([]);
const followedThreads = ref([]);

onMounted(() => {
  savedThreads.value = readThreadList('thread-saved');
  followedThreads.value = readThreadList('thread-followed');
});

function readThreadList(key) {
  try {
    const stored = JSON.parse(localStorage.getItem(key) ?? '[]');
    return Array.isArray(stored) ? stored : [];
  } catch {
    return [];
  }
}

function toggleThreadList(list, key, id) {
  list.value = list.value.includes(id) ? list.value.filter((item) => item !== id) : [...list.value, id];

  try {
    localStorage.setItem(key, JSON.stringify(list.value));
  } catch {
    // A browser with storage blocked still gets the in-page state.
  }
}

const isSaved = computed(() => Boolean(props.thread && savedThreads.value.includes(props.thread.id)));
const isFollowed = computed(() => Boolean(props.thread && followedThreads.value.includes(props.thread.id)));

// --- Rail list ------------------------------------------------------------

const search = ref('');
const threadTopic = ref('');

const visibleThreads = computed(() => {
  const term = search.value.trim().toLowerCase().replace(/^#/, '');

  return props.threads.filter((thread) => {
    if (threadTopic.value && thread.topic !== threadTopic.value) return false;
    if (!term) return true;

    return thread.title.toLowerCase().includes(term) || thread.topic.toLowerCase().includes(term);
  });
});

function applyTopic(topic) {
  threadTopic.value = threadTopic.value === topic ? '' : topic;
}

function clearFilters() {
  threadTopic.value = '';
  search.value = '';
}

// --- Thread composer -------------------------------------------------------

const topLevel = computed(() => sortReplies(replies.value, sort.value));
const sortLabel = computed(() => REPLY_SORTS.find(([key]) => key === sort.value)?.[1] ?? 'Oldest first');

const form = useForm({ title: '', body: '', topic: '' });
const customTopic = ref('');
const customTopicError = computed(() => hashtagError(customTopic.value));

function pickTopic(topic) {
  form.topic = topic;
  customTopic.value = '';
}

function useCustomTopic() {
  const normalized = normalizeHashtag(customTopic.value);
  customTopic.value = normalized;
  if (!hashtagError(normalized)) form.topic = normalized;
}

function submitThread() {
  form.post(route('threads.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      customTopic.value = '';
      composerOpen.value = false;
    },
  });
}

function toggleReply(id) {
  replyError.value = '';
  replyingTo.value = replyingTo.value === id ? null : id;
  if (replyingTo.value === null) replyDraft.value = '';
}

async function postReply(parentId, body) {
  const text = body.trim();

  if (!text) {
    replyError.value = 'Write a reply before posting.';
    return false;
  }

  posting.value = true;
  replyError.value = '';

  try {
    const { data } = await window.axios.post(route('threads.replies.store', props.thread.id), {
      body: text,
      parent_id: parentId,
    });

    // The server decides where the reply actually landed — past the indent cap
    // it becomes a sibling — so the tree is grown from what came back.
    if (!insertReply(replies.value, data.item)) {
      replies.value.push(data.item);
    }

    replyCount.value = data.replyCount ?? countReplies(replies.value);
    return true;
  } catch (error) {
    if (error.response?.status === 422) {
      replyError.value = error.response.data?.errors?.body?.[0] ?? 'Your reply could not be posted.';
    } else if (error.response?.status === 429) {
      replyError.value = 'You are replying too fast. Give it a moment.';
    } else {
      replyError.value = 'Something went wrong while posting your reply.';
    }
    return false;
  } finally {
    posting.value = false;
  }
}

async function submitReply(parentId) {
  if (await postReply(parentId, replyDraft.value)) {
    replyDraft.value = '';
    replyingTo.value = null;
  }
}

async function submitRootReply() {
  if (await postReply(null, rootDraft.value)) {
    rootDraft.value = '';
  }
}

async function vote(replyId, value) {
  const node = findReply(replies.value, replyId);
  if (!node) return;

  try {
    const { data } = await window.axios.post(route('threads.replies.vote', replyId), { value });
    node.score = data.score;
    node.your_vote = data.your_vote;
  } catch {
    flash('Your vote did not go through. Try again.');
  }
}

async function reportReply(replyId, reason) {
  try {
    await window.axios.post(route('threads.replies.report', replyId), { reason });
    reportedReplies.value = [...reportedReplies.value, replyId];
    flash('Reported. A moderator will take a look.');
  } catch {
    flash('That report did not go through. Try again.');
  }
}

async function reportThread(reason) {
  threadMenuOpen.value = false;

  try {
    await window.axios.post(route('threads.report', props.thread.id), { reason });
    threadReported.value = true;
    flash('Reported. A moderator will take a look.');
  } catch {
    flash('That report did not go through. Try again.');
  }
}

async function share(replyId = null) {
  const url = replyId
    ? `${window.location.origin}${route('threads.show', props.thread.id, false)}#reply-${replyId}`
    : `${window.location.origin}${route('threads.show', props.thread.id, false)}`;

  try {
    await navigator.clipboard.writeText(url);
    flash('Link copied.');
  } catch {
    // Clipboard access is blocked outside a secure context; show the link so
    // it can still be copied by hand rather than failing silently.
    flash(url);
  }
}

let noticeTimer = null;

function flash(message) {
  notice.value = message;
  window.clearTimeout(noticeTimer);
  noticeTimer = window.setTimeout(() => { notice.value = ''; }, 4000);
}

function reasonLabel(reason) {
  return REASON_LABELS[reason] ?? reason;
}

function plural(count, one, many) {
  return `${count} ${count === 1 ? one : many}`;
}

provide('threadActions', {
  replyingTo,
  replyDraft,
  replyError,
  posting,
  reportedReplies,
  reportReasons: props.reportReasons,
  maxReplyLength: MAX_REPLY,
  toggleReply,
  submitReply,
  vote,
  share,
  reportReply,
  reasonLabel,
});
</script>

<template>
  <Head :title="thread ? thread.title : 'Threads'" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search threads">
    <div class="th">
      <header class="hero">
        <h1>Long-form talk, still anonymous</h1>
        <p class="hero-lede">
          Ask for advice, argue a point, or just talk it out. Threads are public
          and nothing here is tied to your name.
        </p>
        <div class="hero-actions">
          <button type="button" class="btn btn-green" @click="composerOpen = !composerOpen">
            {{ composerOpen ? 'Close composer' : 'Start a thread' }}
            <span v-if="!composerOpen" aria-hidden="true">&rarr;</span>
          </button>
          <a href="#topics" class="btn btn-plain">Browse topics</a>
          <p class="hero-proof">
            <span>{{ plural(threadStats.totalThreads, 'thread', 'threads') }} · {{ plural(threadStats.totalReplies, 'reply', 'replies') }}</span>
          </p>
        </div>
      </header>

      <form v-if="composerOpen" class="composer-card" @submit.prevent="submitThread">
        <h2>Start a thread</h2>

        <label class="field">
          <span>Title</span>
          <input
            v-model="form.title"
            type="text"
            :maxlength="MAX_TITLE"
            placeholder="How do you manage a heavy course load?"
          />
        </label>
        <p v-if="form.errors.title" class="form-error">{{ form.errors.title }}</p>

        <label class="field">
          <span>What do you want to talk about?</span>
          <textarea
            v-model="form.body"
            rows="5"
            :maxlength="MAX_BODY"
            placeholder="Give people enough context to reply to…"
          ></textarea>
        </label>
        <p v-if="form.errors.body" class="form-error">{{ form.errors.body }}</p>

        <div class="field">
          <span>Topic</span>
          <div class="chips">
            <button
              v-for="topic in topics"
              :key="topic"
              type="button"
              class="chip"
              :class="{ active: form.topic === topic }"
              @click="pickTopic(topic)"
            >
              #{{ topic }}
            </button>
          </div>
          <div class="custom-topic">
            <input
              v-model="customTopic"
              type="text"
              maxlength="30"
              placeholder="or write your own"
              @blur="useCustomTopic"
              @keydown.enter.prevent="useCustomTopic"
            />
            <span v-if="form.topic" class="picked">Using #{{ form.topic }}</span>
          </div>
          <p v-if="customTopicError" class="form-error">{{ customTopicError }}</p>
          <p v-else-if="form.errors.topic" class="form-error">{{ form.errors.topic }}</p>
        </div>

        <div class="composer-foot">
          <p>Threads are public and anonymous. Nothing here is tied to your name.</p>
          <button type="submit" class="btn btn-green" :disabled="form.processing">
            {{ form.processing ? 'Posting…' : 'Post thread' }}
          </button>
        </div>
      </form>

      <p v-if="notice" class="notice">{{ notice }}</p>

      <p v-if="!thread" class="empty">
        No threads yet. Start the first one and give the forum something to talk about.
      </p>

      <template v-else>
        <article class="thread-card">
          <header class="thread-head">
            <img src="/images/branding/bsufw-mark-64.png" alt="" class="thread-avatar" />
            <div class="thread-author">
              <span class="thread-name">
                Anonymous
                <span v-if="thread.is_yours" class="you">you</span>
              </span>
              <span class="thread-meta">
                <time :datetime="thread.created_at" :title="formatDateTime(thread.created_at)">
                  {{ timeAgo(thread.created_at) }}
                </time>
                <i aria-hidden="true">•</i>
                <span>Public</span>
              </span>
            </div>
            <button type="button" class="topic-tag" @click="applyTopic(thread.topic)">#{{ thread.topic }}</button>
          </header>

          <h2 class="thread-title">{{ thread.title }}</h2>
          <p class="thread-body">{{ thread.body }}</p>

          <footer class="thread-foot">
            <span class="metrics">
              <span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M4 5h16v11H9l-5 4v-4H4V5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                </svg>
                {{ replyCount }} replies
              </span>
              <span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6" />
                </svg>
                {{ thread.views_count }} views
              </span>
            </span>

            <span class="thread-actions">
              <button
                type="button"
                class="action"
                :class="{ on: isFollowed }"
                @click="toggleThreadList(followedThreads, 'thread-followed', thread.id)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M6 9a6 6 0 0 1 12 0c0 4 1.4 5.5 2 6H4c.6-.5 2-2 2-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                {{ isFollowed ? 'Following' : 'Follow' }}
              </button>

              <button type="button" class="action" @click="share()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle cx="18" cy="5" r="2.6" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="6" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="18" cy="19" r="2.6" stroke="currentColor" stroke-width="1.6" />
                  <path d="m8.4 10.8 7.2-4.2M8.4 13.2l7.2 4.2" stroke="currentColor" stroke-width="1.6" />
                </svg>
                Share
              </button>

              <button
                type="button"
                class="action"
                :class="{ on: isSaved }"
                @click="toggleThreadList(savedThreads, 'thread-saved', thread.id)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M6 4h12v16l-6-4.2L6 20V4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                </svg>
                {{ isSaved ? 'Saved' : 'Save' }}
              </button>

              <span class="menu-wrap">
                <button
                  type="button"
                  class="action more"
                  :aria-expanded="threadMenuOpen"
                  aria-label="More actions"
                  @click="threadMenuOpen = !threadMenuOpen"
                >
                  &hellip;
                </button>
                <div v-if="threadMenuOpen" class="menu">
                  <p class="menu-title">Report this thread</p>
                  <button v-for="reason in reportReasons" :key="reason" type="button" @click="reportThread(reason)">
                    {{ reasonLabel(reason) }}
                  </button>
                </div>
              </span>

              <span v-if="threadReported" class="reported">Reported</span>
            </span>
          </footer>
        </article>

        <div class="replies-bar">
          <h3>{{ replyCount }} {{ replyCount === 1 ? 'reply' : 'replies' }}</h3>
          <label class="sort">
            <span>Sort</span>
            <select v-model="sort" :aria-label="`Sort replies, currently ${sortLabel}`">
              <option v-for="[value, label] in REPLY_SORTS" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
        </div>

        <ul v-if="topLevel.length" class="tree">
          <ThreadReplyNode v-for="reply in topLevel" :key="reply.id" :reply="reply" :sort="sort" />
        </ul>
        <p v-else class="empty">No replies yet. Be the first to answer.</p>

        <form class="reply-composer" @submit.prevent="submitRootReply">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="thread-avatar" />
          <label class="sr-only" for="th-root-reply">Your reply</label>
          <textarea
            id="th-root-reply"
            v-model="rootDraft"
            rows="1"
            :maxlength="MAX_REPLY"
            placeholder="Write a reply anonymously…"
            @keydown.enter.exact.prevent="submitRootReply"
          ></textarea>
          <button type="submit" class="btn btn-green" :disabled="posting">
            {{ posting ? 'Posting…' : 'Reply' }}
          </button>
        </form>
        <p v-if="replyError && replyingTo === null" class="form-error">{{ replyError }}</p>
      </template>
    </div>

    <template #rail>
      <p class="rail-stat">
        <span>Threads</span>
        <strong>{{ threadStats.totalThreads }}</strong>
        <i>· {{ plural(threadStats.totalReplies, 'reply', 'replies') }}</i>
      </p>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Latest threads</h2>
          <button v-if="threadTopic || search" type="button" class="rail-all" @click="clearFilters">Clear</button>
        </div>

        <ul v-if="visibleThreads.length" class="thread-list">
          <li v-for="item in visibleThreads" :key="item.id" :class="{ active: item.id === thread?.id }">
            <Link :href="`/threads/${item.id}`">
              <strong>{{ item.title }}</strong>
              <small>
                {{ item.reply_count }} {{ item.reply_count === 1 ? 'reply' : 'replies' }}
                <i aria-hidden="true">•</i>
                {{ timeAgo(item.last_activity_at) }}
                <em v-if="savedThreads.includes(item.id)">saved</em>
                <em v-else-if="followedThreads.includes(item.id)">following</em>
              </small>
            </Link>
          </li>
        </ul>
        <p v-else class="empty">{{ threads.length ? 'No threads match that filter.' : 'No threads yet.' }}</p>
      </section>

      <section id="topics" class="rail-card">
        <div class="rail-head">
          <h2>Topics</h2>
        </div>
        <div class="chips">
          <button
            v-for="topic in topics"
            :key="topic"
            type="button"
            class="chip"
            :class="{ active: threadTopic === topic }"
            @click="applyTopic(topic)"
          >
            #{{ topic }}
          </button>
        </div>
      </section>

      <section v-if="trending.length" class="rail-card">
        <div class="rail-head">
          <h2>Trending</h2>
        </div>
        <ol class="trending">
          <li v-for="(item, index) in trending" :key="item.id">
            <span class="rank">{{ index + 1 }}</span>
            <Link :href="`/threads/${item.id}`">
              <strong>{{ item.title }}</strong>
              <small>
                {{ item.views_count }} views
                <i aria-hidden="true">•</i>
                {{ item.reply_count }} {{ item.reply_count === 1 ? 'reply' : 'replies' }}
              </small>
            </Link>
          </li>
        </ol>
      </section>

      <section v-if="activeTags.length" class="rail-card">
        <div class="rail-head">
          <h2>Active tags</h2>
        </div>
        <div class="chips">
          <button
            v-for="tag in activeTags"
            :key="tag"
            type="button"
            class="chip"
            :class="{ active: threadTopic === tag }"
            @click="applyTopic(tag)"
          >
            #{{ tag }}
          </button>
        </div>
      </section>

      <section class="rail-card rail-promo">
        <h2>House rules</h2>
        <ol class="rules">
          <li v-for="guideline in GUIDELINES" :key="guideline">{{ guideline }}</li>
        </ol>
        <button type="button" class="btn btn-green btn-block" @click="composerOpen = true">
          Start a thread <span aria-hidden="true">&rarr;</span>
        </button>
      </section>
    </template>
  </DirectoryLayout>
</template>

<style scoped>
/* The directory skin, same tokens as the wall. */

.th {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: th / inline-size;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
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
  transition: background 0.15s ease, border-color 0.15s ease;
}

.btn-green {
  background: var(--d-green);
  color: var(--d-green-ink);
}

.btn-green:hover:not(:disabled) {
  background: var(--d-green-hover);
}

.btn-green:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-plain {
  background: var(--d-card);
  border-color: var(--d-line);
  color: var(--d-ink);
}

.btn-plain:hover {
  border-color: var(--d-line-strong);
}

.btn-block {
  width: 100%;
  margin-top: 0.9rem;
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

.rail-card .empty {
  padding: 0.3rem 0;
  border: 0;
  background: none;
  font-size: 0.8rem;
  text-align: left;
}

.form-error {
  margin: 0.3rem 0 0;
  font-size: 0.78rem;
  color: #dc2626;
}

.notice {
  margin: 0;
  padding: 0.7rem 0.95rem;
  background: var(--d-green-tint);
  border: 1px solid var(--d-green-tint-line);
  border-radius: var(--d-r-md);
  font-size: 0.84rem;
  color: var(--d-green-text);
  overflow-wrap: anywhere;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chip {
  padding: 0.3rem 0.65rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  font: inherit;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--d-muted);
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
}

.chip:hover {
  border-color: var(--d-green);
  color: var(--d-green-text);
}

.chip.active {
  background: var(--d-green);
  border-color: var(--d-green);
  color: var(--d-green-ink);
}

/* --- Hero ----------------------------------------------------------------- */

.hero {
  padding: 1.5rem 0 0.25rem;
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
  margin-top: 1.25rem;
}

.hero-proof {
  margin: 0 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--d-muted);
}

/* --- Thread composer ------------------------------------------------------ */

.composer-card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.1rem 1.2rem 1.2rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.composer-card h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 750;
  letter-spacing: -0.02em;
  color: var(--d-ink);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.field > span {
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-ink);
}

.field input,
.field textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  font: inherit;
  font-size: 0.9rem;
  line-height: 1.55;
  color: var(--d-ink);
  resize: vertical;
}

.field input:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--d-green);
  background: var(--d-card);
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--d-faint);
}

.custom-topic {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.1rem;
}

.custom-topic input {
  max-width: 14rem;
  padding: 0.4rem 0.7rem;
  border-radius: var(--d-r-pill);
  font-size: 0.8rem;
}

.picked {
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-green-text);
}

.composer-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding-top: 0.3rem;
}

.composer-foot p {
  margin: 0;
  font-size: 0.76rem;
  color: var(--d-muted);
}

/* --- Open thread ---------------------------------------------------------- */

.thread-card {
  padding: 1rem 1.15rem 0.85rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.thread-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.thread-avatar {
  width: 2.1rem;
  height: 2.1rem;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  object-fit: cover;
}

.thread-author {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.thread-name {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--d-ink);
}

.you {
  padding: 0.05rem 0.35rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
}

.thread-meta {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.74rem;
  color: var(--d-muted);
}

.thread-meta i {
  font-style: normal;
  color: var(--d-faint);
}

.topic-tag {
  padding: 0.25rem 0.6rem;
  border: 1px solid var(--d-green-tint-line);
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  font: inherit;
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--d-green-text);
  cursor: pointer;
}

.thread-title {
  margin: 0.85rem 0 0.5rem;
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.028em;
  line-height: 1.25;
  color: var(--d-ink);
}

.thread-body {
  margin: 0;
  font-size: 0.93rem;
  line-height: 1.65;
  color: var(--d-ink-2);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.thread-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 0.9rem;
  padding-top: 0.6rem;
  border-top: 1px solid var(--d-line);
}

.metrics {
  display: inline-flex;
  align-items: center;
  gap: 0.9rem;
  font-size: 0.78rem;
  color: var(--d-muted);
}

.metrics span {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.thread-actions {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.35rem 0.55rem;
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

.action.on {
  color: var(--d-green-text);
}

.menu-wrap {
  position: relative;
}

.menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.3rem);
  z-index: 10;
  width: 13rem;
  padding: 0.5rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  box-shadow: var(--d-shadow-lift);
}

.menu-title {
  margin: 0 0 0.35rem;
  padding: 0 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.menu button {
  display: block;
  width: 100%;
  padding: 0.4rem;
  border: 0;
  border-radius: var(--d-r-sm);
  background: none;
  font: inherit;
  font-size: 0.82rem;
  color: var(--d-ink-2);
  text-align: left;
  cursor: pointer;
}

.menu button:hover {
  background: var(--d-soft);
  color: var(--d-ink);
}

.reported {
  font-size: 0.74rem;
  font-weight: 650;
  color: var(--d-green-text);
}

/* --- Replies -------------------------------------------------------------- */

.replies-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 0.4rem;
}

.replies-bar h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--d-ink);
}

.sort {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: var(--d-muted);
}

.sort select {
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-sm);
  background: var(--d-card);
  font: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--d-ink);
}

.tree {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.reply-composer {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.6rem 0.7rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  box-shadow: var(--d-shadow);
}

.reply-composer textarea {
  flex: 1;
  min-width: 0;
  max-height: 7rem;
  padding: 0.4rem 0.2rem;
  border: 0;
  background: none;
  font: inherit;
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--d-ink);
  resize: none;
}

.reply-composer textarea:focus {
  outline: none;
}

.reply-composer textarea::placeholder {
  color: var(--d-faint);
}

.reply-composer .thread-avatar {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 50%;
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
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: none;
}

.rail-card {
  padding: 0.9rem 0.95rem 1rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.6rem;
}

.rail-head h2,
.rail-promo h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-all {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-green-text);
  cursor: pointer;
}

.thread-list,
.trending {
  margin: 0;
  padding: 0;
  list-style: none;
}

.thread-list :deep(a),
.trending :deep(a) {
  display: block;
  padding: 0.45rem 0.5rem;
  border-radius: var(--d-r-md);
  text-decoration: none;
  transition: background 0.15s ease;
}

.thread-list :deep(a:hover),
.trending :deep(a:hover) {
  background: var(--d-soft);
}

.thread-list li.active :deep(a) {
  background: var(--d-green-tint);
}

.thread-list strong,
.trending strong {
  display: block;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 0.82rem;
  font-weight: 650;
  line-height: 1.4;
  color: var(--d-ink);
}

.thread-list small,
.trending small {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.7rem;
  color: var(--d-muted);
}

.thread-list i,
.trending i {
  font-style: normal;
  color: var(--d-faint);
}

.thread-list em {
  margin-left: 0.3rem;
  padding: 0.05rem 0.3rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  color: var(--d-green-text);
  font-style: normal;
  font-size: 0.62rem;
  font-weight: 700;
  text-transform: uppercase;
}

.trending li {
  display: flex;
  align-items: flex-start;
  gap: 0.4rem;
}

.trending li :deep(a) {
  flex: 1;
  min-width: 0;
}

.rank {
  padding-top: 0.5rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--d-faint);
}

.rules {
  margin: 0;
  padding-left: 1.05rem;
  font-size: 0.8rem;
  line-height: 1.5;
  color: var(--d-muted);
}

.rules li {
  margin-bottom: 0.35rem;
}

/* --- Responsive ----------------------------------------------------------- */

@container th (max-width: 620px) {
  .hero h1 {
    font-size: 1.8rem;
  }

  .thread-title {
    font-size: 1.2rem;
  }

  .thread-foot {
    flex-direction: column;
    align-items: flex-start;
  }

  .composer-foot {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
