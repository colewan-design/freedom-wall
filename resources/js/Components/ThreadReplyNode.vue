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
.tr-node {
  position: relative;
  list-style: none;
}

/* The elbow from the parent's rail into this reply. */
.tr-node.nested::before {
  content: '';
  position: absolute;
  top: 19px;
  left: -21px;
  width: 15px;
  height: 1px;
  background: var(--nf-line);
}

.tr-reply {
  display: flex;
  align-items: flex-start;
  gap: 0.8rem;
  padding: 0.85rem 0;
}

.tr-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-thumb);
}

.tr-avatar img {
  width: 22px;
  height: 22px;
  object-fit: contain;
}

.tr-avatar.muted {
  border-style: dashed;
}

.tr-removed {
  margin: 0;
  padding: 0.5rem 0;
  color: var(--nf-muted);
  font-size: 0.88rem;
  font-style: italic;
}

.tr-body {
  flex: 1;
  min-width: 0;
}

.tr-head {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0 0 0.3rem;
}

.tr-head strong {
  font-size: 0.9rem;
}

.tr-head time {
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
}

.tr-you {
  padding: 1px 6px;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-pill);
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.tr-text {
  margin: 0;
  font-size: 0.93rem;
  line-height: 1.6;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.tr-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.9rem;
  margin-top: 0.55rem;
}

.tr-votes {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.tr-vote {
  display: inline-flex;
  padding: 2px;
  background: none;
  border: 0;
  color: var(--nf-muted);
  cursor: pointer;
  transition: color 0.15s var(--b-ease);
}

.tr-vote:hover {
  color: var(--b-900);
}

.tr-vote.on {
  color: var(--nf-accent);
}

.tr-votes b {
  min-width: 1.1rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}

.tr-votes b.up {
  color: var(--nf-accent);
}

.tr-votes b.down {
  color: var(--danger);
}

.tr-action {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
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

.tr-action:hover {
  color: var(--nf-accent);
}

.tr-more {
  font-size: 15px;
  line-height: 1;
}

.tr-menu-wrap {
  position: relative;
  display: inline-flex;
}

.tr-menu {
  position: absolute;
  z-index: 5;
  top: calc(100% + 8px);
  left: 0;
  display: flex;
  flex-direction: column;
  min-width: 172px;
  padding: 0.35rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-sm);
}

.tr-menu-title {
  margin: 0;
  padding: 0.4rem 0.5rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.8px;
  text-transform: uppercase;
}

.tr-menu button {
  padding: 0.45rem 0.5rem;
  background: none;
  border: 0;
  color: inherit;
  font-size: 0.85rem;
  text-align: left;
  cursor: pointer;
}

.tr-menu button:hover {
  background: var(--b-50);
  color: var(--nf-accent);
}

.tr-reported {
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 11px;
}

.tr-inline-form {
  margin-top: 0.7rem;
}

.tr-inline-form textarea {
  width: 100%;
  padding: 0.6rem 0.7rem;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-input);
  color: inherit;
  font: inherit;
  font-size: 0.9rem;
  resize: vertical;
}

.tr-inline-form textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.tr-inline-error {
  margin: 0.4rem 0 0;
  color: var(--danger);
  font-size: 0.82rem;
}

.tr-inline-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.tr-inline-actions button {
  padding: 0.42rem 0.9rem;
  background: var(--nf-accent);
  border: 1px solid var(--nf-accent);
  border-radius: var(--b-r-sm);
  color: var(--b-green-ink);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  cursor: pointer;
}

.tr-inline-actions button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.tr-inline-actions .ghost {
  background: none;
  border-color: var(--nf-line);
  color: var(--nf-muted);
}

.tr-children {
  margin: 0 0 0 17px;
  padding: 0 0 0 21px;
  border-left: 1px solid var(--nf-line);
}
</style>
