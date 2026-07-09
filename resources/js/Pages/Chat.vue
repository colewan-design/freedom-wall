<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import NewsfeedLayout from '../Layouts/NewsfeedLayout.vue';
import { timeAgo } from '../lib/date';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  messages: { type: Array, required: true },
  chatNickname: { type: String, required: true },
});

const POLL_INTERVAL_MS = 4000;

const messages = ref([...props.messages]);
const content = ref('');
const sending = ref(false);
const errorMessage = ref('');
const status = ref('Live updates every 4 seconds');
const listRef = ref(null);

let pollTimer = null;

const latestId = computed(() => messages.value.at(-1)?.id ?? 0);

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

async function fetchMessages() {
  try {
    const wasNearBottom = isNearBottom();
    const { data } = await window.axios.get(route('chat.messages.index'), {
      params: { after_id: latestId.value },
    });

    if (Array.isArray(data.items) && data.items.length) {
      messages.value.push(...data.items);
      status.value = 'New messages loaded';
      if (wasNearBottom) {
        await scrollToBottom();
      }
    } else {
      status.value = 'Live updates every 4 seconds';
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
    content.value = '';
    status.value = 'Message sent';
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
});
</script>

<template>
  <section class="chat-page">
    <div class="chat-hero">
      <div>
        <span class="chat-eyebrow">Global Room</span>
        <h1>Campus chat, live and anonymous.</h1>
        <p>Everyone joins the same room. Your nickname stays with your current session, and fresh messages appear automatically.</p>
      </div>
      <div class="chat-nickname-card">
        <span class="label">You are</span>
        <strong>{{ chatNickname }}</strong>
        <span class="status">{{ status }}</span>
      </div>
    </div>

    <div class="chat-shell">
      <div ref="listRef" class="chat-stream">
        <article v-for="message in messages" :key="message.id" class="chat-bubble">
          <div class="chat-meta">
            <strong>{{ message.nickname }}</strong>
            <span>{{ timeAgo(message.sent_at) }}</span>
          </div>
          <p>{{ message.content }}</p>
        </article>

        <p v-if="messages.length === 0" class="empty-state">No messages yet. Start the room.</p>
      </div>

      <form class="chat-composer" @submit.prevent="sendMessage">
        <label for="chat-message" class="sr-only">Message</label>
        <textarea
          id="chat-message"
          v-model="content"
          rows="3"
          maxlength="500"
          placeholder="Say something to the room…"
        ></textarea>

        <div class="composer-footer">
          <p class="composer-note">Keep it respectful. Messages are capped at 500 characters.</p>
          <button type="submit" class="send-btn" :disabled="sending">
            {{ sending ? 'Sending…' : 'Send message' }}
          </button>
        </div>

        <p v-if="errorMessage" class="chat-error">{{ errorMessage }}</p>
      </form>
    </div>
  </section>
</template>

<style scoped>
.chat-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.chat-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 240px;
  gap: 1rem;
  padding: 1.5rem;
  border: 1px solid var(--nf-line);
  border-radius: 18px;
  background: var(--nf-hero-grad);
}

.chat-eyebrow {
  display: inline-block;
  margin-bottom: 0.55rem;
  color: var(--nf-accent);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.chat-hero h1 {
  margin: 0 0 0.45rem;
  font-size: 1.8rem;
  line-height: 1.1;
}

.chat-hero p {
  margin: 0;
  color: var(--nf-muted);
  max-width: 52ch;
  line-height: 1.55;
}

.chat-nickname-card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  justify-content: center;
  padding: 1rem;
  background: color-mix(in srgb, var(--nf-panel) 84%, transparent);
  border: 1px solid var(--nf-line);
  border-radius: 16px;
}

.chat-nickname-card .label,
.chat-nickname-card .status {
  color: var(--nf-muted);
  font-size: 0.8rem;
}

.chat-nickname-card strong {
  font-size: 1.2rem;
}

.chat-shell {
  display: grid;
  grid-template-rows: minmax(420px, 68vh) auto;
  gap: 1rem;
}

.chat-stream,
.chat-composer {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 18px;
}

.chat-stream {
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.chat-bubble {
  max-width: min(720px, 100%);
  padding: 0.9rem 1rem;
  border-radius: 16px;
  background: var(--nf-surface-2);
  border: 1px solid color-mix(in srgb, var(--nf-line) 75%, transparent);
}

.chat-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.4rem;
  font-size: 0.8rem;
}

.chat-meta strong {
  color: var(--nf-accent);
}

.chat-meta span {
  color: var(--nf-muted);
}

.chat-bubble p {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.5;
}

.empty-state {
  margin: auto;
  color: var(--nf-muted);
}

.chat-composer {
  padding: 1rem;
}

.chat-composer textarea {
  width: 100%;
  min-height: 110px;
  resize: vertical;
  padding: 0.9rem 1rem;
  border-radius: 14px;
  border: 1px solid var(--nf-line);
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
  line-height: 1.5;
}

.chat-composer textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--nf-accent) 20%, transparent);
}

.composer-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 0.8rem;
}

.composer-note {
  margin: 0;
  font-size: 0.8rem;
  color: var(--nf-muted);
}

.send-btn {
  border: none;
  border-radius: 999px;
  padding: 0.7rem 1.1rem;
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-weight: 700;
  cursor: pointer;
}

.send-btn:disabled {
  opacity: 0.65;
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
  }

  .composer-footer {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
