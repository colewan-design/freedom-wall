<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import NewsfeedLayout from '../Layouts/NewsfeedLayout.vue';
import { dayKey, dayLabel, formatDateTime, timeOfDay } from '../lib/date';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  messages: { type: Array, required: true },
  chatNickname: { type: String, required: true },
  chatStats: { type: Object, default: () => ({ totalMessages: 0, pollLabel: 'Every 4 sec' }) },
});

const POLL_INTERVAL_MS = 4000;
const MAX_LENGTH = 500;

// Room controls live in the sidebar; fall back to sane defaults if this page is
// ever rendered outside the newsfeed shell.
const room = inject('chatRoom', null);
const autoScroll = room?.autoScroll ?? ref(true);
const soundAlerts = room?.soundAlerts ?? ref(false);
const nickname = room?.nickname ?? computed(() => props.chatNickname);

const messages = ref([...props.messages]);
const content = ref('');
const sending = ref(false);
const errorMessage = ref('');
const status = ref('Live updates every 4 seconds.');
const receivedSinceLoad = ref(0);
const listRef = ref(null);

let pollTimer = null;
let audioContext = null;

// Each message shows a wall-clock time only, so the day it belongs to has to be
// stated somewhere or an 11 AM message reads as out of order sitting under a
// 6 PM one from the night before. Mark every message that opens a new day.
const rows = computed(() =>
  messages.value.map((message, index) => {
    const previous = messages.value[index - 1];

    return {
      message,
      day: previous && dayKey(previous.sent_at) === dayKey(message.sent_at)
        ? null
        : dayLabel(message.sent_at),
    };
  }),
);

const latestId = computed(() => messages.value.at(-1)?.id ?? 0);
const remaining = computed(() => MAX_LENGTH - content.value.length);
const canSend = computed(() => content.value.trim().length > 0 && !sending.value);

// The stream only holds the latest slice, so the headline count tracks the
// all-time total the server sent plus whatever has landed since.
const totalMessages = computed(() => (props.chatStats?.totalMessages ?? 0) + receivedSinceLoad.value);

function initials(name) {
  return (name ?? '').slice(0, 2).toUpperCase();
}

function isOwn(message) {
  return message.nickname === nickname.value;
}

function isNearBottom() {
  const element = listRef.value;
  if (!element) return true;
  return element.scrollHeight - element.scrollTop - element.clientHeight < 120;
}

async function scrollToBottom() {
  await nextTick();
  const element = listRef.value;
  if (element) {
    element.scrollTop = element.scrollHeight;
  }
}

// A short synthesized ping — no asset to ship, and silence if the browser
// blocks audio until the first gesture.
function playPing() {
  try {
    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return;
    audioContext ??= new Ctor();
    if (audioContext.state === 'suspended') audioContext.resume();

    const start = audioContext.currentTime;
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();

    osc.type = 'sine';
    osc.frequency.setValueAtTime(880, start);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(0.07, start + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.24);

    osc.connect(gain).connect(audioContext.destination);
    osc.start(start);
    osc.stop(start + 0.25);
  } catch {
    // Audio is a nicety; never break the room over it.
  }
}

async function fetchMessages() {
  try {
    const wasNearBottom = isNearBottom();
    const { data } = await window.axios.get(route('chat.messages.index'), {
      params: { after_id: latestId.value },
    });

    if (data.presence) {
      room?.setPresence?.(data.presence);
    }

    if (Array.isArray(data.items) && data.items.length) {
      const fromOthers = data.items.filter((item) => !isOwn(item));

      messages.value.push(...data.items);
      receivedSinceLoad.value += data.items.length;
      status.value = 'New messages loaded.';

      if (fromOthers.length && soundAlerts.value) {
        playPing();
      }

      if (autoScroll.value && wasNearBottom) {
        await scrollToBottom();
      }
    } else {
      status.value = 'Live updates every 4 seconds.';
    }
  } catch {
    status.value = 'Trying to reconnect…';
  }
}

async function sendMessage() {
  if (sending.value) return;

  const text = content.value.trim();
  if (!text) {
    errorMessage.value = 'Write a message before sending.';
    return;
  }

  sending.value = true;
  errorMessage.value = '';

  try {
    const { data } = await window.axios.post(route('chat.messages.store'), {
      content: text,
    });

    messages.value.push(data.item);
    receivedSinceLoad.value += 1;
    content.value = '';
    status.value = 'Message sent.';
    await scrollToBottom();
  } catch (error) {
    if (error.response?.status === 422) {
      errorMessage.value = error.response.data?.errors?.content?.[0] ?? 'Your message could not be sent.';
    } else if (error.response?.status === 429) {
      errorMessage.value = 'You are sending too fast. Please wait a bit.';
    } else {
      errorMessage.value = 'Something went wrong while sending your message.';
    }
  } finally {
    sending.value = false;
  }
}

onMounted(async () => {
  await scrollToBottom();
  pollTimer = window.setInterval(fetchMessages, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
  if (pollTimer) {
    window.clearInterval(pollTimer);
  }
  audioContext?.close?.();
});
</script>

<template>
  <section class="chat-page">
    <header class="chat-hero">
      <div class="chat-hero-copy">
        <span class="chat-eyebrow">01 &mdash; Global Room</span>
        <h1>Campus chat, live and anonymous.</h1>
        <p>
          Everyone joins the same room. Your nickname stays with your current session,
          and fresh messages appear automatically.
        </p>
        <p class="chat-hero-meta">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 5h16v11H9l-5 4v-4H4V5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
          </svg>
          <span>{{ totalMessages }} messages</span>
          <i aria-hidden="true">&bull;</i>
          <span>refresh every 4 sec</span>
        </p>
      </div>

      <aside class="chat-nickname-card">
        <span class="label">You are</span>
        <strong>{{ nickname }}</strong>
        <span class="status">{{ status }}</span>
      </aside>
    </header>

    <div ref="listRef" class="chat-stream">
      <template v-for="row in rows" :key="row.message.id">
        <p v-if="row.day" class="chat-day"><span>{{ row.day }}</span></p>

        <article class="chat-row" :class="{ own: isOwn(row.message) }">
          <span class="chat-avatar" aria-hidden="true">{{ initials(row.message.nickname) }}</span>
          <div class="chat-row-body">
            <div class="chat-row-head">
              <strong>{{ row.message.nickname }}</strong>
              <span v-if="isOwn(row.message)" class="chat-you">you</span>
              <time :datetime="row.message.sent_at" :title="formatDateTime(row.message.sent_at)">
                {{ timeOfDay(row.message.sent_at) }}
              </time>
            </div>
            <p>{{ row.message.content }}</p>
          </div>
        </article>
      </template>

      <p v-if="messages.length === 0" class="empty-state">No messages yet. Start the room.</p>
    </div>

    <form class="chat-composer" @submit.prevent="sendMessage">
      <label for="chat-message" class="sr-only">Message</label>
      <div class="composer-field">
        <textarea
          id="chat-message"
          v-model="content"
          rows="3"
          :maxlength="MAX_LENGTH"
          placeholder="Say something to the room…"
          @keydown.enter.exact.prevent="sendMessage"
        ></textarea>
        <span class="composer-count" :class="{ low: remaining <= 50 }">
          {{ content.length }} / {{ MAX_LENGTH }}
        </span>
      </div>

      <div class="composer-footer">
        <p class="composer-note">Keep it respectful. Messages are capped at {{ MAX_LENGTH }} characters.</p>
        <button type="submit" class="send-btn" :disabled="!canSend">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 3 3 10.5l7.2 2.8L13 21l8-18Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
          </svg>
          {{ sending ? 'Sending…' : 'Send message' }}
        </button>
      </div>

      <p v-if="errorMessage" class="chat-error">{{ errorMessage }}</p>
    </form>
  </section>
</template>

<style scoped>
.chat-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.chat-hero,
.chat-stream,
.chat-composer {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-md);
}

/* --- Hero ---------------------------------------------------------------- */
.chat-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 232px;
  align-items: center;
  gap: 1.5rem;
  padding: 1.75rem;
}

.chat-eyebrow {
  display: inline-block;
  margin-bottom: 0.7rem;
  color: var(--nf-accent);
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.1px;
  text-transform: uppercase;
}

.chat-hero h1 {
  margin: 0 0 0.6rem;
  font-size: 2rem;
  line-height: 1.12;
  letter-spacing: -0.02em;
}

.chat-hero-copy > p {
  margin: 0;
  max-width: 54ch;
  color: var(--nf-muted);
  font-size: 0.95rem;
  line-height: 1.6;
}

.chat-hero-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1rem;
  font-family: var(--b-mono);
  font-size: 12px;
  color: var(--nf-muted);
}

.chat-hero-meta svg {
  flex: 0 0 auto;
}

.chat-hero-meta i {
  font-style: normal;
  opacity: 0.6;
}

.chat-nickname-card {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  padding: 1.1rem 1.15rem;
  background: var(--b-50);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-sm);
}

.chat-nickname-card .label {
  color: var(--nf-muted);
  font-size: 0.82rem;
}

.chat-nickname-card strong {
  font-size: 1.15rem;
  line-height: 1.2;
  overflow-wrap: anywhere;
}

.chat-nickname-card .status {
  margin-top: 0.15rem;
  color: var(--nf-muted);
  font-size: 0.78rem;
  line-height: 1.45;
}

/* --- Stream -------------------------------------------------------------- */
.chat-stream {
  height: clamp(360px, 56vh, 620px);
  overflow-y: auto;
  padding: 0.35rem 1.25rem;
}

.chat-row {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 0.95rem 0;
  border-bottom: 1px solid var(--nf-line);
}

.chat-row:last-child {
  border-bottom: 0;
}

.chat-day {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  padding: 0.9rem 0 0.2rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.chat-day::before,
.chat-day::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--nf-line);
}

.chat-day:first-child {
  padding-top: 0.35rem;
}

/* The row above already draws a rule; let the day marker carry it instead. */
.chat-row:has(+ .chat-day) {
  border-bottom: 0;
}

.chat-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 36px;
  height: 36px;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-thumb);
  background: var(--b-50);
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  letter-spacing: 1px;
}

.chat-row.own .chat-avatar {
  border-color: color-mix(in srgb, var(--nf-accent) 45%, var(--nf-line));
  color: var(--nf-accent);
}

.chat-row-body {
  flex: 1;
  min-width: 0;
}

.chat-row-head {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin-bottom: 0.3rem;
}

.chat-row-head strong {
  color: var(--nf-accent);
  font-size: 0.92rem;
  overflow-wrap: anywhere;
}

.chat-you {
  padding: 1px 6px;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-pill);
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.chat-row-head time {
  margin-left: auto;
  flex: 0 0 auto;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
}

.chat-row-body p {
  margin: 0;
  color: var(--nf-ink);
  font-size: 0.95rem;
  line-height: 1.55;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.empty-state {
  margin: 0;
  padding: 3rem 0;
  color: var(--nf-muted);
  text-align: center;
}

/* --- Composer ------------------------------------------------------------ */
.chat-composer {
  padding: 1.1rem 1.25rem 1.25rem;
}

.composer-field {
  position: relative;
}

.composer-field textarea {
  width: 100%;
  min-height: 92px;
  resize: vertical;
  padding: 0.85rem 1rem 1.9rem;
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-input);
  background: var(--b-50);
  color: var(--nf-ink);
  font: inherit;
  font-size: 0.95rem;
  line-height: 1.55;
}

.composer-field textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.composer-count {
  position: absolute;
  right: 0.8rem;
  bottom: 0.65rem;
  color: var(--nf-muted);
  font-family: var(--b-mono);
  font-size: 11px;
  pointer-events: none;
}

.composer-count.low {
  color: var(--nf-accent);
}

.composer-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 0.9rem;
}

.composer-note {
  margin: 0;
  color: var(--nf-muted);
  font-size: 0.82rem;
}

.send-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  flex: 0 0 auto;
  padding: 0.7rem 1.15rem;
  border: 1px solid var(--nf-accent);
  border-radius: var(--b-r-input);
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.2s ease;
}

.send-btn:hover:not(:disabled) {
  background: var(--b-green-hover);
  border-color: var(--b-green-hover);
}

.send-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.chat-error {
  margin: 0.8rem 0 0;
  color: #f87171;
  font-size: 0.85rem;
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

@media (max-width: 900px) {
  .chat-hero {
    grid-template-columns: 1fr;
    align-items: stretch;
    padding: 1.35rem;
  }

  .chat-hero h1 {
    font-size: 1.6rem;
  }

  .composer-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .send-btn {
    justify-content: center;
  }
}
</style>
