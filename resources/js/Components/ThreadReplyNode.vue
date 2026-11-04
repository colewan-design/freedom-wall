<script setup>
import { computed, inject, ref } from 'vue';
import { formatDateTime, timeAgo } from '../lib/date';
import { sortReplies } from '../lib/threads';

const props = defineProps({
  reply: { type: Object, required: true },
  sort: { type: String, default: 'oldest' },
});

// The tree renders itself, so handing every level its own set of callbacks
// through props would mean re-emitting each event at every depth.
const thread = inject('threadActions');

const menuOpen = ref(false);
const children = computed(() => sortReplies(props.reply.children, props.sort));
const isReplying = computed(() => thread.replyingTo.value === props.reply.id);
const reported = computed(() => thread.reportedReplies.value.includes(props.reply.id));

function report(reason) {
  menuOpen.value = false;
  thread.reportReply(props.reply.id, reason);
}
</script>

<template>
  <li class="tr-node" :class="{ nested: reply.depth > 0 }">
    <article class="tr-reply">
      <span class="tr-avatar" :class="{ muted: reply.removed }" aria-hidden="true">
        <img v-if="!reply.removed" src="/images/branding/bsufw-mark-64.png" alt="" />
      </span>

      <div v-if="reply.removed" class="tr-body">
        <p class="tr-removed">Reply removed by a moderator.</p>
      </div>

      <div v-else class="tr-body">
        <p class="tr-head">
          <strong>Anonymous</strong>
          <span v-if="reply.is_yours" class="tr-you">you</span>
          <time :datetime="reply.created_at" :title="formatDateTime(reply.created_at)">
            {{ timeAgo(reply.created_at) }}
          </time>
        </p>

        <p class="tr-text">{{ reply.body }}</p>

        <div class="tr-actions">
          <span class="tr-votes">
            <button
              type="button"
              class="tr-vote"
              :class="{ on: reply.your_vote === 1 }"
              :aria-pressed="reply.your_vote === 1"
              aria-label="Upvote this reply"
              @click="thread.vote(reply.id, 1)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 19V5M6 11l6-6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
            <b :class="{ up: reply.score > 0, down: reply.score < 0 }">{{ reply.score }}</b>
            <button
              type="button"
              class="tr-vote"
              :class="{ on: reply.your_vote === -1 }"
              :aria-pressed="reply.your_vote === -1"
              aria-label="Downvote this reply"
              @click="thread.vote(reply.id, -1)"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 5v14M6 13l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </span>

          <button type="button" class="tr-action" @click="thread.toggleReply(reply.id)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M9 7 4 12l5 5M4 12h9a7 7 0 0 1 7 7v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Reply
          </button>

          <button type="button" class="tr-action" @click="thread.share(reply.id)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="18" cy="5" r="2.6" stroke="currentColor" stroke-width="1.6" />
              <circle cx="6" cy="12" r="2.6" stroke="currentColor" stroke-width="1.6" />
              <circle cx="18" cy="19" r="2.6" stroke="currentColor" stroke-width="1.6" />
              <path d="m8.4 10.8 7.2-4.2M8.4 13.2l7.2 4.2" stroke="currentColor" stroke-width="1.6" />
            </svg>
            Share
          </button>

          <span class="tr-menu-wrap">
            <button
              type="button"
              class="tr-action tr-more"
              :aria-expanded="menuOpen"
              aria-label="More actions"
              @click="menuOpen = !menuOpen"
            >
              &hellip;
            </button>

            <div v-if="menuOpen" class="tr-menu">
              <p class="tr-menu-title">Report this reply</p>
              <button
                v-for="reason in thread.reportReasons"
                :key="reason"
                type="button"
                @click="report(reason)"
              >
                {{ thread.reasonLabel(reason) }}
              </button>
            </div>
          </span>

          <span v-if="reported" class="tr-reported">Reported &mdash; thanks.</span>
        </div>

        <form v-if="isReplying" class="tr-inline-form" @submit.prevent="thread.submitReply(reply.id)">
          <textarea
            v-model="thread.replyDraft.value"
            rows="2"
            :maxlength="thread.maxReplyLength"
            placeholder="Write a reply anonymously…"
            aria-label="Your reply"
            @keydown.esc="thread.toggleReply(reply.id)"
          ></textarea>
          <p v-if="thread.replyError.value" class="tr-inline-error">{{ thread.replyError.value }}</p>
          <div class="tr-inline-actions">
            <button type="submit" :disabled="thread.posting.value">
              {{ thread.posting.value ? 'Posting…' : 'Post reply' }}
            </button>
            <button type="button" class="ghost" @click="thread.toggleReply(reply.id)">Cancel</button>
          </div>
        </form>
      </div>
    </article>

    <ul v-if="children.length" class="tr-children">
      <ThreadReplyNode
        v-for="child in children"
        :key="child.id"
        :reply="child"
        :sort="sort"
      />
    </ul>
  </li>
</template>

<style scoped>
/* The directory skin: this node is rendered inside the threads page, which
   reads the --d-* tokens rather than the newsfeed shell's --nf-* ones. */

.tr-node {
  position: relative;
  list-style: none;
}

/* The elbow from the parent's rail into this reply. */
.tr-node.nested::before {
  content: '';
  position: absolute;
  top: 24px;
  left: -21px;
  width: 15px;
  height: 1px;
  background: var(--d-line);
}

.tr-reply {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
  padding: 0.75rem 0.9rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.tr-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 2rem;
  height: 2rem;
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-sm);
}

.tr-avatar img {
  width: 1.3rem;
  height: 1.3rem;
  object-fit: contain;
}

.tr-avatar.muted {
  border-style: dashed;
}

.tr-removed {
  margin: 0;
  padding: 0.35rem 0;
  color: var(--d-faint);
  font-size: 0.86rem;
  font-style: italic;
}

.tr-body {
  flex: 1;
  min-width: 0;
}

.tr-head {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  margin: 0 0 0.25rem;
}

.tr-head strong {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--d-ink);
}

.tr-head time {
  font-size: 0.72rem;
  color: var(--d-faint);
}

.tr-you {
  padding: 0.05rem 0.35rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.tr-text {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--d-ink-2);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.tr-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.tr-votes {
  display: inline-flex;
  align-items: center;
  gap: 0.15rem;
  padding: 0.1rem 0.25rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
}

.tr-vote {
  display: inline-flex;
  padding: 0.15rem;
  background: none;
  border: 0;
  border-radius: 50%;
  color: var(--d-muted);
  cursor: pointer;
  transition: color 0.15s var(--b-ease);
}

.tr-vote:hover {
  color: var(--d-ink);
}

.tr-vote.on {
  color: var(--d-green-text);
}

.tr-votes b {
  min-width: 1.1rem;
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--d-ink);
  text-align: center;
}

.tr-votes b.up {
  color: var(--d-green-text);
}

.tr-votes b.down {
  color: #dc2626;
}

.tr-action {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.3rem 0.5rem;
  background: none;
  border: 0;
  border-radius: var(--d-r-sm);
  color: var(--d-muted);
  font: inherit;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.tr-action:hover {
  background: var(--d-soft);
  color: var(--d-ink);
}

.tr-more {
  font-size: 0.95rem;
  line-height: 1;
}

.tr-menu-wrap {
  position: relative;
  display: inline-flex;
}

.tr-menu {
  position: absolute;
  z-index: 5;
  top: calc(100% + 6px);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 11.5rem;
  padding: 0.4rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  box-shadow: var(--d-shadow-lift);
}

.tr-menu-title {
  margin: 0;
  padding: 0.25rem 0.4rem 0.35rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.tr-menu button {
  padding: 0.4rem;
  background: none;
  border: 0;
  border-radius: var(--d-r-sm);
  color: var(--d-ink-2);
  font: inherit;
  font-size: 0.82rem;
  text-align: left;
  cursor: pointer;
}

.tr-menu button:hover {
  background: var(--d-soft);
  color: var(--d-ink);
}

.tr-reported {
  font-size: 0.74rem;
  font-weight: 650;
  color: var(--d-green-text);
}

.tr-inline-form {
  margin-top: 0.6rem;
}

.tr-inline-form textarea {
  width: 100%;
  padding: 0.6rem 0.7rem;
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.88rem;
  line-height: 1.5;
  resize: vertical;
}

.tr-inline-form textarea:focus {
  outline: none;
  border-color: var(--d-green);
  background: var(--d-card);
}

.tr-inline-form textarea::placeholder {
  color: var(--d-faint);
}

.tr-inline-error {
  margin: 0.4rem 0 0;
  font-size: 0.8rem;
  color: #dc2626;
}

.tr-inline-actions {
  display: flex;
  gap: 0.4rem;
  margin-top: 0.5rem;
}

.tr-inline-actions button {
  padding: 0.45rem 0.9rem;
  background: var(--d-green);
  border: 1px solid var(--d-green);
  border-radius: var(--d-r-md);
  color: var(--d-green-ink);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 650;
  cursor: pointer;
}

.tr-inline-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.tr-inline-actions .ghost {
  background: var(--d-card);
  border-color: var(--d-line);
  color: var(--d-ink);
}

.tr-children {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin: 0.6rem 0 0 17px;
  padding: 0 0 0 21px;
  border-left: 1px solid var(--d-line);
}
</style>
