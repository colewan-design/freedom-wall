<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DirectoryLayout from '../Layouts/DirectoryLayout.vue';
import { dayKey, dayLabel, formatDateTime, shortTimeAgo, timeOfDay } from '../lib/date';

// The room controls used to live in the newsfeed shell and reach the stream
// through a provide. They sit in this page's own rail now, so the state is
// plain local state and nothing has to be handed across a layout boundary.

const props = defineProps({
  messages: { type: Array, required: true },
  chatNickname: { type: String, required: true },
  chatStats: { type: Object, default: () => ({ totalMessages: 0, messagesToday: 0, pollLabel: 'Every 4 sec' }) },
  chatPresence: { type: Object, default: () => ({ members: [], onlineCount: 0 }) },
});

const POLL_INTERVAL_MS = 4000;
const MAX_LENGTH = 500;

const messages = ref([...props.messages]);
const content = ref('');
const sending = ref(false);
const errorMessage = ref('');
const status = ref('Live updates every 4 seconds.');
const receivedSinceLoad = ref(0);
const listRef = ref(null);

let pollTimer = null;
let audioContext = null;

// --- Room controls ---------------------------------------------------------

const autoScroll = ref(true);
const soundAlerts = ref(false);

watch(autoScroll, (value) => localStorage.setItem('chat-auto-scroll', value ? 'on' : 'off'));
watch(soundAlerts, (value) => localStorage.setItem('chat-sound-alerts', value ? 'on' : 'off'));

const nicknameOverride = ref(null);
const presenceOverride = ref(null);

const nickname = computed(() => nicknameOverride.value ?? props.chatNickname);
const presence = computed(() => presenceOverride.value ?? props.chatPresence);

const editingNickname = ref(false);
const nicknameDraft = ref('');
const nicknameError = ref('');
const savingNickname = ref(false);
const nicknameInput = ref(null);

// A re-render resets session-scoped props, so drop stale overrides when
// Inertia swaps the page out from under us.
watch(() => props.chatNickname, () => { nicknameOverride.value = null; });
watch(() => props.chatPresence, () => { presenceOverride.value = null; });

function openNicknameEditor() {
  editingNickname.value = !editingNickname.value;
  if (!editingNickname.value) return;
  nicknameDraft.value = nickname.value;
  nicknameError.value = '';
  window.requestAnimationFrame(() => {
    nicknameInput.value?.focus();
    nicknameInput.value?.select();
  });
}

function cancelNicknameEdit() {
  editingNickname.value = false;
  nicknameError.value = '';
}

async function saveNickname() {
  const next = nicknameDraft.value.trim();

  if (next === nickname.value) {
    cancelNicknameEdit();
    return;
  }

  savingNickname.value = true;
  nicknameError.value = '';

  try {
    const { data } = await window.axios.post(route('chat.nickname.update'), { nickname: next });
    nicknameOverride.value = data.nickname;
    editingNickname.value = false;
  } catch (error) {
    if (error.response?.status === 422) {
      nicknameError.value = error.response.data?.errors?.nickname?.[0] ?? 'That nickname could not be used.';
    } else if (error.response?.status === 429) {
      nicknameError.value = 'Too many changes. Try again in a few minutes.';
    } else {
      nicknameError.value = 'Something went wrong. Try again.';
    }
  } finally {
    savingNickname.value = false;
  }
}

// --- Stream ----------------------------------------------------------------

const search = ref('');

const visibleMessages = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return messages.value;
  return messages.value.filter(
    (message) =>
      message.content?.toLowerCase().includes(term) || message.nickname?.toLowerCase().includes(term),
  );
});

// Each message shows a wall-clock time only, so the day it belongs to has to be
// stated somewhere or an 11 AM message reads as out of order sitting under a
// 6 PM one from the night before. Mark every message that opens a new day.
const rows = computed(() =>
  visibleMessages.value.map((message, index) => {
    const previous = visibleMessages.value[index - 1];

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
      presenceOverride.value = data.presence;
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
  autoScroll.value = localStorage.getItem('chat-auto-scroll') !== 'off';
  soundAlerts.value = localStorage.getItem('chat-sound-alerts') === 'on';
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
  <Head title="Chat" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search messages">
    <div class="chat">
      <header class="hero">
        <h1>Campus chat, live and anonymous</h1>
        <p class="hero-lede">
          Everyone joins the same room. Your nickname stays with this session,
          and new messages appear on their own.
        </p>
        <div class="hero-actions">
          <span class="live-pill">
            <i class="dot" aria-hidden="true"></i>
            {{ presence.onlineCount }} in the room
          </span>
          <span class="hero-meta">{{ totalMessages.toLocaleString() }} messages · refreshes every 4 sec</span>
        </div>
      </header>

      <section class="stream-card">
        <div class="stream-head">
          <div>
            <p class="card-kicker">Global room</p>
            <p class="stream-title">You are {{ nickname }}</p>
          </div>
          <span class="stream-status">{{ status }}</span>
        </div>

        <div ref="listRef" class="stream">
          <template v-for="row in rows" :key="row.message.id">
            <p v-if="row.day" class="day-rule"><span>{{ row.day }}</span></p>

            <article class="msg" :class="{ own: isOwn(row.message) }">
              <span class="msg-avatar" aria-hidden="true">{{ initials(row.message.nickname) }}</span>
              <div class="msg-body">
                <div class="msg-head">
                  <strong>{{ row.message.nickname }}</strong>
                  <span v-if="isOwn(row.message)" class="msg-you">you</span>
                  <time :datetime="row.message.sent_at" :title="formatDateTime(row.message.sent_at)">
                    {{ timeOfDay(row.message.sent_at) }}
                  </time>
                </div>
                <p>{{ row.message.content }}</p>
              </div>
            </article>
          </template>

          <p v-if="rows.length === 0" class="empty">
            {{ messages.length === 0 ? 'No messages yet. Start the room.' : 'No messages match your search.' }}
          </p>
        </div>

        <form class="composer" @submit.prevent="sendMessage">
          <label for="chat-message" class="sr-only">Message</label>
          <div class="composer-field">
            <textarea
              id="chat-message"
              v-model="content"
              rows="2"
              :maxlength="MAX_LENGTH"
              placeholder="Say something to the room…"
              @keydown.enter.exact.prevent="sendMessage"
            ></textarea>
            <span class="composer-count" :class="{ low: remaining <= 50 }">
              {{ content.length }} / {{ MAX_LENGTH }}
            </span>
          </div>

          <div class="composer-foot">
            <p>Keep it respectful. Messages are capped at {{ MAX_LENGTH }} characters.</p>
            <button type="submit" class="btn btn-green" :disabled="!canSend">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M21 3 3 10.5l7.2 2.8L13 21l8-18Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
              </svg>
              {{ sending ? 'Sending…' : 'Send' }}
            </button>
          </div>

          <p v-if="errorMessage" class="form-error">{{ errorMessage }}</p>
        </form>
      </section>
    </div>

    <template #rail>
      <p class="rail-stat">
        <span>Messages</span>
        <strong>{{ totalMessages.toLocaleString() }}</strong>
        <i>· {{ chatStats.messagesToday }} today</i>
      </p>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Room controls</h2>
        </div>

        <button type="button" class="room-row room-action" :aria-expanded="editingNickname" @click="openNicknameEditor">
          <span class="room-label">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
            </svg>
            Change nickname
          </span>
          <svg class="room-chevron" :class="{ open: editingNickname }" width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>

        <form v-if="editingNickname" class="nickname-form" @submit.prevent="saveNickname">
          <input
            ref="nicknameInput"
            v-model="nicknameDraft"
            type="text"
            maxlength="20"
            aria-label="New nickname"
            placeholder="NewNickname12"
            @keydown.esc="cancelNicknameEdit"
          />
          <div class="nickname-actions">
            <button type="submit" class="btn btn-green btn-sm" :disabled="savingNickname">
              {{ savingNickname ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" class="btn btn-plain btn-sm" @click="cancelNicknameEdit">Cancel</button>
          </div>
          <p v-if="nicknameError" class="form-error">{{ nicknameError }}</p>
          <p v-else class="form-hint">3–20 letters or numbers.</p>
        </form>

        <div class="room-row">
          <span class="room-label">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 9a8 8 0 0 1 13.3-3.3L20 8M20 15a8 8 0 0 1-13.3 3.3L4 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M20 4v4h-4M4 20v-4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Auto-scroll
          </span>
          <button
            type="button"
            class="switch"
            role="switch"
            :aria-checked="autoScroll"
            aria-label="Auto-scroll to newest messages"
            :class="{ on: autoScroll }"
            @click="autoScroll = !autoScroll"
          >
            <span class="switch-knob"></span>
          </button>
        </div>

        <div class="room-row">
          <span class="room-label">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9a6 6 0 0 1 12 0c0 4 1.4 5.5 2 6H4c.6-.5 2-2 2-6Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              <path d="M10 19a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
            Sound alerts
          </span>
          <button
            type="button"
            class="switch"
            role="switch"
            :aria-checked="soundAlerts"
            aria-label="Play a sound for new messages"
            :class="{ on: soundAlerts }"
            @click="soundAlerts = !soundAlerts"
          >
            <span class="switch-knob"></span>
          </button>
        </div>

        <p class="room-note">These settings only apply to your session in this room.</p>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Active recently</h2>
          <span class="online-badge"><i class="dot" aria-hidden="true"></i>{{ presence.onlineCount }} online</span>
        </div>

        <ul v-if="presence.members.length" class="presence">
          <li v-for="member in presence.members" :key="member.nickname">
            <span class="tile tile-sm" aria-hidden="true">{{ initials(member.nickname) }}</span>
            <span class="presence-name" :class="{ self: member.nickname === nickname }">{{ member.nickname }}</span>
            <time :datetime="member.lastSentAt">{{ shortTimeAgo(member.lastSentAt) }}</time>
          </li>
        </ul>
        <p v-else class="empty">The room is quiet right now.</p>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Room snapshot</h2>
        </div>
        <ul class="rail-stats">
          <li><span>Total messages</span><strong>{{ totalMessages.toLocaleString() }}</strong></li>
          <li><span>Messages today</span><strong>{{ chatStats.messagesToday }}</strong></li>
          <li><span>Refresh pace</span><strong>{{ chatStats.pollLabel }}</strong></li>
          <li><span>Your nickname</span><strong>{{ nickname }}</strong></li>
        </ul>
      </section>
    </template>
  </DirectoryLayout>
</template>

<style scoped>
/* The directory skin, same tokens as the wall. */

.chat {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: chat / inline-size;
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
  padding: 0.6rem 1.05rem;
  border: 1px solid transparent;
  border-radius: var(--d-r-md);
  font: inherit;
  font-size: 0.86rem;
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

.btn-sm {
  padding: 0.4rem 0.75rem;
  font-size: 0.8rem;
}

.card-kicker {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--d-muted);
}

.tile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-weight: 800;
  line-height: 1;
}

.tile-sm {
  width: 1.65rem;
  height: 1.65rem;
  font-size: 0.64rem;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

.empty {
  margin: 0;
  padding: 1rem 0.3rem;
  font-size: 0.84rem;
  color: var(--d-muted);
  text-align: center;
}

.form-error {
  margin: 0.4rem 0 0;
  font-size: 0.78rem;
  color: #dc2626;
}

.form-hint {
  margin: 0.4rem 0 0;
  font-size: 0.74rem;
  color: var(--d-faint);
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
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-top: 1.1rem;
}

.live-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.8rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  color: var(--d-green-text);
  font-size: 0.8rem;
  font-weight: 700;
}

.hero-meta {
  font-size: 0.82rem;
  color: var(--d-muted);
}

/* --- Stream --------------------------------------------------------------- */

.stream-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.stream-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 0.9rem 1.1rem;
  border-bottom: 1px solid var(--d-line);
}

.stream-title {
  margin: 0.15rem 0 0;
  font-size: 1.1rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--d-ink);
}

.stream-status {
  font-size: 0.76rem;
  color: var(--d-muted);
}

.stream {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  height: min(58vh, 560px);
  padding: 1rem 1.1rem;
  overflow-y: auto;
  background: var(--d-soft);
}

.day-rule {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0.5rem 0 0.2rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.day-rule::before,
.day-rule::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--d-line);
}

.msg {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  max-width: 85%;
}

.msg.own {
  flex-direction: row-reverse;
  margin-left: auto;
}

.msg-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--d-muted);
}

.msg-body {
  padding: 0.5rem 0.75rem;
  border-radius: var(--d-r-md);
  background: var(--d-card);
  border: 1px solid var(--d-line);
}

.msg.own .msg-body {
  background: var(--d-green-tint);
  border-color: var(--d-green-tint-line);
}

.msg-head {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  margin-bottom: 0.15rem;
}

.msg-head strong {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--d-ink);
}

.msg-you {
  padding: 0.05rem 0.35rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
}

.msg-head time {
  font-size: 0.68rem;
  color: var(--d-faint);
}

.msg-body p {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--d-ink-2);
  overflow-wrap: anywhere;
}

/* --- Composer ------------------------------------------------------------- */

.composer {
  padding: 0.85rem 1.1rem 1rem;
  border-top: 1px solid var(--d-line);
}

.composer-field {
  position: relative;
}

.composer-field textarea {
  width: 100%;
  padding: 0.7rem 0.85rem 1.5rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  font: inherit;
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--d-ink);
  resize: vertical;
}

.composer-field textarea:focus {
  outline: none;
  border-color: var(--d-green);
  background: var(--d-card);
}

.composer-field textarea::placeholder {
  color: var(--d-faint);
}

.composer-count {
  position: absolute;
  right: 0.7rem;
  bottom: 0.5rem;
  font-size: 0.7rem;
  color: var(--d-faint);
}

.composer-count.low {
  color: #dc2626;
}

.composer-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 0.65rem;
}

.composer-foot p {
  margin: 0;
  font-size: 0.76rem;
  color: var(--d-muted);
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

.rail-head h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.online-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.18rem 0.5rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  color: var(--d-green-text);
  font-size: 0.68rem;
  font-weight: 700;
}

.room-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
  padding: 0.45rem 0;
  border: 0;
  border-bottom: 1px dashed var(--d-line);
  background: none;
  font: inherit;
  text-align: left;
}

.room-row:last-of-type {
  border-bottom: 0;
}

.room-action {
  cursor: pointer;
}

.room-label {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.82rem;
  color: var(--d-ink-2);
}

.room-label svg {
  color: var(--d-muted);
}

.room-action:hover .room-label {
  color: var(--d-green-text);
}

.room-chevron {
  color: var(--d-faint);
  transition: transform 0.15s ease;
}

.room-chevron.open {
  transform: rotate(90deg);
}

.room-note {
  margin: 0.6rem 0 0;
  font-size: 0.72rem;
  line-height: 1.5;
  color: var(--d-faint);
}

.nickname-form {
  padding: 0.5rem 0 0.7rem;
}

.nickname-form input {
  width: 100%;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-sm);
  background: var(--d-soft);
  font: inherit;
  font-size: 0.82rem;
  color: var(--d-ink);
}

.nickname-form input:focus {
  outline: none;
  border-color: var(--d-green);
}

.nickname-actions {
  display: flex;
  gap: 0.35rem;
  margin-top: 0.45rem;
}

.switch {
  position: relative;
  width: 2.1rem;
  height: 1.15rem;
  flex-shrink: 0;
  padding: 0;
  border: 1px solid var(--d-line-strong);
  border-radius: var(--d-r-pill);
  background: var(--d-soft);
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.switch.on {
  background: var(--d-green);
  border-color: var(--d-green);
}

.switch-knob {
  position: absolute;
  top: 1px;
  left: 1px;
  width: 0.85rem;
  height: 0.85rem;
  border-radius: 50%;
  background: var(--d-card);
  transition: transform 0.15s var(--b-ease);
}

.switch.on .switch-knob {
  transform: translateX(0.93rem);
}

.presence {
  margin: 0;
  padding: 0;
  list-style: none;
}

.presence li {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.3rem 0;
}

.presence-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--d-ink);
}

.presence-name.self {
  color: var(--d-green-text);
}

.presence time {
  font-size: 0.7rem;
  color: var(--d-faint);
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
  gap: 0.5rem;
  padding: 0.38rem 0;
  border-bottom: 1px dashed var(--d-line);
  font-size: 0.82rem;
  color: var(--d-muted);
}

.rail-stats li:last-child {
  border-bottom: 0;
}

.rail-stats strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 700;
  color: var(--d-ink);
}

/* --- Responsive ----------------------------------------------------------- */

@container chat (max-width: 620px) {
  .hero h1 {
    font-size: 1.8rem;
  }

  .msg {
    max-width: 95%;
  }

  .composer-foot {
    flex-direction: column;
    align-items: stretch;
  }
}

@media (prefers-reduced-motion: reduce) {
  .switch-knob {
    transition: none;
  }
}
</style>
