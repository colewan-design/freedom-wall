<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, inject, provide, ref, watch } from 'vue';
import ThreadReplyNode from '../../Components/ThreadReplyNode.vue';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';
import { formatDateTime, timeAgo } from '../../lib/date';
import { hashtagError, normalizeHashtag } from '../../lib/hashtag';
import { REPLY_SORTS, countReplies, findReply, insertReply, sortReplies } from '../../lib/threads';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  thread: { type: Object, default: null },
  topics: { type: Array, default: () => [] },
  reportReasons: { type: Array, default: () => [] },
});

const MAX_TITLE = 160;
const MAX_BODY = 4000;
const MAX_REPLY = 2000;

const REASON_LABELS = {
  harassment: 'Harassment or bullying',
  'personal-info': 'Shares personal info',
  spam: 'Spam or advertising',
  hate: 'Hate speech',
  other: 'Something else',
};

// Save/follow live in the shell so the thread list in the sidebar can mark the
// ones you kept, the same way the chat room shares its controls.
const room = inject('threadRoom', null);

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

const topLevel = computed(() => sortReplies(replies.value, sort.value));
const sortLabel = computed(() => REPLY_SORTS.find(([key]) => key === sort.value)?.[1] ?? 'Oldest first');
const isSaved = computed(() => Boolean(props.thread && room?.isSaved(props.thread.id)));
const isFollowed = computed(() => Boolean(props.thread && room?.isFollowed(props.thread.id)));

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

  <section class="th-page">
    <header class="th-hero">
      <span class="th-eyebrow">03 &mdash; Community Forum</span>
      <h1>Threads</h1>
      <p>
        Long-form anonymous discussions for the BSU community.<br />
        Share advice, ask questions, or just talk it out.
      </p>

      <div class="th-hero-actions">
        <button type="button" class="th-primary-btn" @click="composerOpen = !composerOpen">
          {{ composerOpen ? 'Close composer' : 'Start a thread' }}
        </button>
        <a href="#thread-topics" class="th-ghost-link">Browse topics <span aria-hidden="true">&rarr;</span></a>
      </div>
    </header>

    <form v-if="composerOpen" class="th-composer" @submit.prevent="submitThread">
      <label class="th-field">
        <span>Title</span>
        <input
          v-model="form.title"
          type="text"
          :maxlength="MAX_TITLE"
          placeholder="How do you manage heavy course load?"
        />
      </label>
      <p v-if="form.errors.title" class="th-error">{{ form.errors.title }}</p>

      <label class="th-field">
        <span>What do you want to talk about?</span>
        <textarea
          v-model="form.body"
          rows="5"
          :maxlength="MAX_BODY"
          placeholder="Give people enough context to reply to…"
        ></textarea>
      </label>
      <p v-if="form.errors.body" class="th-error">{{ form.errors.body }}</p>

      <div class="th-topic-picker">
        <span class="th-field-label">Topic</span>
        <div class="th-chips">
          <button
            v-for="topic in topics"
            :key="topic"
            type="button"
            class="th-chip"
            :class="{ active: form.topic === topic }"
            @click="pickTopic(topic)"
          >
            #{{ topic }}
          </button>
        </div>
        <div class="th-custom-topic">
          <input
            v-model="customTopic"
            type="text"
            maxlength="30"
            placeholder="or write your own"
            @blur="useCustomTopic"
            @keydown.enter.prevent="useCustomTopic"
          />
          <span v-if="form.topic" class="th-picked">Using #{{ form.topic }}</span>
        </div>
        <p v-if="customTopicError" class="th-error">{{ customTopicError }}</p>
        <p v-else-if="form.errors.topic" class="th-error">{{ form.errors.topic }}</p>
      </div>

      <div class="th-composer-footer">
        <p>Threads are public and anonymous. Nothing here is tied to your name.</p>
        <button type="submit" class="th-primary-btn" :disabled="form.processing">
          {{ form.processing ? 'Posting…' : 'Post thread' }}
        </button>
      </div>
    </form>

    <p v-if="notice" class="th-notice">{{ notice }}</p>

    <p v-if="!thread" class="th-empty">
      No threads yet. Start the first one and give the forum something to talk about.
    </p>

    <template v-else>
      <article class="th-card">
        <header class="th-card-head">
          <span class="th-avatar" aria-hidden="true">
            <img src="/images/branding/bsufw-mark-64.png" alt="" />
          </span>
          <div class="th-card-author">
            <strong>Anonymous</strong>
            <span v-if="thread.is_yours" class="th-you">you</span>
            <small>
              <time :datetime="thread.created_at" :title="formatDateTime(thread.created_at)">
                {{ timeAgo(thread.created_at) }}
              </time>
              <i aria-hidden="true">/</i> Public
            </small>
          </div>
          <span class="th-topic-tag">#{{ thread.topic }}</span>
        </header>

        <h2 class="th-title is-content">{{ thread.title }}</h2>
        <p class="th-card-body">{{ thread.body }}</p>

        <footer class="th-card-foot">
          <span class="th-metrics">
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

          <span class="th-card-actions">
            <button type="button" class="th-action" :class="{ on: isFollowed }" @click="room?.toggleFollow(thread.id)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9a6 6 0 0 1 12 0c0 4 1.4 5.5 2 6H4c.6-.5 2-2 2-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              {{ isFollowed ? 'Following' : 'Follow' }}
            </button>

            <button type="button" class="th-action" @click="share()">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="18" cy="5" r="2.6" stroke="currentColor" stroke-width="1.6" />
                <circle cx="6" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6" />
                <circle cx="18" cy="19" r="2.6" stroke="currentColor" stroke-width="1.6" />
                <path d="m8.4 10.8 7.2-4.2M8.4 13.2l7.2 4.2" stroke="currentColor" stroke-width="1.6" />
              </svg>
              Share
            </button>

            <button type="button" class="th-action" :class="{ on: isSaved }" @click="room?.toggleSave(thread.id)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 4h12v16l-6-4.2L6 20V4Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              </svg>
              {{ isSaved ? 'Saved' : 'Save' }}
            </button>

            <span class="th-menu-wrap">
              <button
                type="button"
                class="th-action th-more"
                :aria-expanded="threadMenuOpen"
                aria-label="More actions"
                @click="threadMenuOpen = !threadMenuOpen"
              >
                &hellip;
              </button>
              <div v-if="threadMenuOpen" class="th-menu">
                <p class="th-menu-title">Report this thread</p>
                <button v-for="reason in reportReasons" :key="reason" type="button" @click="reportThread(reason)">
                  {{ reasonLabel(reason) }}
                </button>
              </div>
            </span>

            <span v-if="threadReported" class="th-reported">Reported</span>
          </span>
        </footer>
      </article>

      <div class="th-replies-bar">
        <h3>{{ replyCount }} {{ replyCount === 1 ? 'reply' : 'replies' }}</h3>
        <label class="th-sort">
          <span>Sort:</span>
          <select v-model="sort" :aria-label="`Sort replies, currently ${sortLabel}`">
            <option v-for="[value, label] in REPLY_SORTS" :key="value" :value="value">{{ label }}</option>
          </select>
        </label>
      </div>

      <ul v-if="topLevel.length" class="th-tree">
        <ThreadReplyNode v-for="reply in topLevel" :key="reply.id" :reply="reply" :sort="sort" />
      </ul>
      <p v-else class="th-empty">No replies yet. Be the first to answer.</p>

      <form class="th-reply-composer" @submit.prevent="submitRootReply">
        <span class="th-avatar" aria-hidden="true">
          <img src="/images/branding/bsufw-mark-64.png" alt="" />
        </span>
        <label class="sr-only" for="th-root-reply">Your reply</label>
        <textarea
          id="th-root-reply"
          v-model="rootDraft"
          rows="1"
          :maxlength="MAX_REPLY"
          placeholder="Write a reply anonymously…"
          @keydown.enter.exact.prevent="submitRootReply"
        ></textarea>
        <button type="submit" class="th-primary-btn" :disabled="posting">
          {{ posting ? 'Posting…' : 'Post reply' }}
        </button>
      </form>
      <p v-if="replyError && replyingTo === null" class="th-error th-reply-error">{{ replyError }}</p>
    </template>
  </section>
</template>

<style scoped>
.th-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.th-hero,
.th-composer,
.th-card,
.th-reply-composer {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-md);
}

/* --- Hero ---------------------------------------------------------------- */
.th-hero {
  padding: 1.75rem;
}

.th-eyebrow {
  display: inline-block;
  margin-bottom: 0.7rem;
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.1px;
  text-transform: uppercase;
}

.th-hero h1 {
  margin: 0 0 0.75rem;
  font-family: var(--b-display);
  font-size: clamp(2.2rem, 6vw, 3.1rem);
  font-weight: 400;
  line-height: 1;
  letter-spacing: -0.01em;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.th-hero > p {
  margin: 0;
  max-width: 60ch;
  color: var(--nf-muted);
  font-size: 0.95rem;
  line-height: 1.65;
}

.th-hero-actions {
  display: flex;
  align-items: center;
  gap: 1.2rem;
  margin-top: 1.3rem;
}

.th-primary-btn {
  padding: 0.7rem 1.35rem;
  background: var(--nf-accent);
  border: 1px solid var(--nf-accent);
  border-radius: var(--b-r-sm);
  color: var(--b-green-ink);
  font-family: var(--b-mono);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.9px;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.15s var(--b-ease);
}

.th-primary-btn:hover:not(:disabled) {
  background: var(--b-green-hover);
  border-color: var(--b-green-hover);
}

.th-primary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.th-ghost-link {
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 12px;
  letter-spacing: 0.7px;
  text-decoration: none;
  text-transform: uppercase;
}

.th-ghost-link:hover {
  color: var(--nf-accent);
}

/* --- Composer ------------------------------------------------------------ */
.th-composer {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  padding: 1.4rem 1.5rem;
}

.th-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.th-field > span,
.th-field-label {
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

.th-field input,
.th-field textarea,
.th-custom-topic input {
  width: 100%;
  padding: 0.65rem 0.75rem;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-input);
  color: inherit;
  font: inherit;
  font-size: 0.93rem;
  resize: vertical;
}

.th-field input:focus,
.th-field textarea:focus,
.th-custom-topic input:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.th-topic-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.th-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.th-chip {
  padding: 0.35rem 0.7rem;
  background: none;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-pill);
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  cursor: pointer;
}

.th-chip:hover,
.th-chip.active {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.th-custom-topic {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.th-custom-topic input {
  max-width: 220px;
}

.th-picked {
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 11px;
}

.th-composer-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--nf-line);
}

.th-composer-footer p {
  margin: 0;
  color: var(--nf-muted);
  font-size: 0.82rem;
}

.th-error {
  margin: 0;
  color: var(--danger);
  font-size: 0.84rem;
}

.th-reply-error {
  padding: 0 0.25rem;
}

.th-notice {
  margin: 0;
  padding: 0.7rem 1rem;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-sm);
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 12px;
  overflow-wrap: anywhere;
}

.th-empty {
  margin: 0;
  padding: 2rem 1.5rem;
  border: 1px dashed var(--nf-line);
  border-radius: var(--b-r-md);
  color: var(--nf-muted);
  font-size: 0.92rem;
  text-align: center;
}

/* --- Thread card --------------------------------------------------------- */
.th-card {
  padding: 1.4rem 1.5rem;
}

.th-card-head {
  display: flex;
  align-items: flex-start;
  gap: 0.8rem;
}

.th-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 38px;
  height: 38px;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-thumb);
}

.th-avatar img {
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.th-card-author {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.th-card-author strong {
  font-size: 0.95rem;
}

.th-card-author small {
  display: inline-flex;
  gap: 0.4rem;
  width: 100%;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.7px;
  text-transform: uppercase;
}

.th-card-author small i {
  font-style: normal;
  opacity: 0.6;
}

.th-you {
  padding: 1px 6px;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-pill);
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.th-topic-tag {
  flex: 0 0 auto;
  padding: 0.3rem 0.6rem;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-pill);
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.th-title {
  margin: 1.1rem 0 0.6rem;
  color: var(--nf-ink);
  font-size: 1.4rem;
  line-height: 1.25;
  letter-spacing: -0.01em;
}

.th-card-body {
  margin: 0;
  color: var(--b-700);
  font-size: 0.96rem;
  line-height: 1.7;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.th-card-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 1.3rem;
  padding-top: 1rem;
  border-top: 1px solid var(--nf-line);
}

.th-metrics {
  display: inline-flex;
  align-items: center;
  gap: 1.4rem;
}

.th-metrics span {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.th-card-actions {
  display: inline-flex;
  align-items: center;
  gap: 1.3rem;
}

.th-action {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0;
  background: none;
  border: 0;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  cursor: pointer;
  transition: color 0.15s var(--b-ease);
}

.th-action:hover,
.th-action.on {
  color: var(--nf-accent);
}

.th-more {
  font-size: 15px;
  line-height: 1;
}

.th-menu-wrap {
  position: relative;
  display: inline-flex;
}

.th-menu {
  position: absolute;
  z-index: 5;
  top: calc(100% + 8px);
  right: 0;
  display: flex;
  flex-direction: column;
  min-width: 180px;
  padding: 0.35rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-sm);
}

.th-menu-title {
  margin: 0;
  padding: 0.4rem 0.5rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

.th-menu button {
  padding: 0.45rem 0.5rem;
  background: none;
  border: 0;
  color: inherit;
  font-size: 0.85rem;
  text-align: left;
  cursor: pointer;
}

.th-menu button:hover {
  background: var(--b-50);
  color: var(--nf-accent);
}

.th-reported {
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 11px;
  text-transform: uppercase;
}

/* --- Replies ------------------------------------------------------------- */
.th-replies-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0 0.25rem;
}

.th-replies-bar h3 {
  margin: 0;
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.9px;
  text-transform: uppercase;
}

.th-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.7px;
  text-transform: uppercase;
}

.th-sort select {
  padding: 0.25rem 0.4rem;
  background: none;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-sm);
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.th-tree {
  margin: 0;
  padding: 0.2rem 1.5rem 0.6rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-md);
  list-style: none;
}

.th-tree > .tr-node + .tr-node {
  border-top: 1px solid var(--nf-line);
}

/* --- Reply composer ------------------------------------------------------ */
.th-reply-composer {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.8rem 0.9rem;
}

.th-reply-composer textarea {
  flex: 1;
  min-width: 0;
  min-height: 42px;
  padding: 0.6rem 0.75rem;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-input);
  color: inherit;
  font: inherit;
  font-size: 0.93rem;
  resize: vertical;
}

.th-reply-composer textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
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

@media (max-width: 720px) {
  .th-card-foot {
    align-items: flex-start;
    flex-direction: column;
  }

  .th-card-actions,
  .th-metrics {
    flex-wrap: wrap;
    gap: 0.9rem;
  }

  .th-reply-composer {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
