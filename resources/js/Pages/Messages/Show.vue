<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import StudentLayout from '../../Layouts/StudentLayout.vue';
import { timeAgo } from '../../lib/date';

defineOptions({ layout: StudentLayout });

const props = defineProps({
  conversation: { type: Object, required: true },
  messages: { type: Array, required: true },
});

const POLL_INTERVAL_MS = 4000;

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);
const isGroup = computed(() => props.conversation.type === 'group');

const messages = ref([...props.messages]);
const content = ref('');
const sending = ref(false);
const errorMessage = ref('');
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
    const { data } = await window.axios.get(route('conversations.messages.fetch', props.conversation.id), {
      params: { after_id: latestId.value },
    });

    if (Array.isArray(data.items) && data.items.length) {
      messages.value.push(...data.items);
      if (wasNearBottom) {
        await scrollToBottom();
      }
    }
  } catch {
    // Silently retry on the next poll tick.
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
    const { data } = await window.axios.post(route('conversations.messages.store', props.conversation.id), {
      content: text,
    });

    messages.value.push(data.item);
    content.value = '';
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

function leaveGroup() {
  if (!confirm('Leave this group chat?')) return;
  router.delete(route('conversations.leave', props.conversation.id));
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
  <section class="thread-page">
    <header class="thread-header">
      <Link :href="route('messages.index')" class="back-btn" aria-label="Back to messages">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 5 8 12l7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </Link>
      <div class="thread-title">
        <h2>{{ conversation.display_name }}</h2>
        <span v-if="isGroup" class="thread-sub">{{ conversation.participants.length }} members</span>
      </div>
      <button v-if="isGroup" type="button" class="leave-btn" @click="leaveGroup">Leave</button>
    </header>

    <div ref="listRef" class="thread-stream">
      <article
        v-for="message in messages"
        :key="message.id"
        class="bubble"
        :class="{ own: message.user_id === currentUserId }"
      >
        <div v-if="isGroup && message.user_id !== currentUserId" class="bubble-sender">
          {{ message.user?.name }}
        </div>
        <p>{{ message.content }}</p>
        <span class="bubble-time">{{ timeAgo(message.created_at) }}</span>
      </article>

      <p v-if="messages.length === 0" class="empty-state">No messages yet. Say hi!</p>
    </div>

    <form class="thread-composer" @submit.prevent="sendMessage">
      <textarea
        v-model="content"
        rows="2"
        maxlength="1000"
        placeholder="Write a message…"
        @keydown.enter.exact.prevent="sendMessage"
      ></textarea>
      <button type="submit" class="send-btn" :disabled="sending">
        {{ sending ? 'Sending…' : 'Send' }}
      </button>
    </form>
    <p v-if="errorMessage" class="thread-error">{{ errorMessage }}</p>
  </section>
</template>

<style scoped>
.thread-page {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.thread-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 0.75rem 1rem;
}

.back-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 50%;
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  flex-shrink: 0;
}

.thread-title {
  flex: 1;
  min-width: 0;
}

.thread-title h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--nf-ink);
}

.thread-sub {
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.leave-btn {
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  border: 1px solid var(--nf-line);
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
}

.leave-btn:hover {
  border-color: #dc2626;
  color: #dc2626;
}

.thread-stream {
  height: min(58vh, 560px);
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.65rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
}

.bubble {
  max-width: min(520px, 84%);
  padding: 0.65rem 0.85rem;
  border-radius: 14px;
  background: var(--nf-surface-2);
  border: 1px solid color-mix(in srgb, var(--nf-line) 75%, transparent);
}

.bubble.own {
  align-self: flex-end;
  background: var(--nf-accent);
  border-color: var(--nf-accent);
}

.bubble-sender {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--nf-accent);
  margin-bottom: 0.2rem;
}

.bubble p {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.45;
  color: var(--nf-ink);
}

.bubble.own p {
  color: #fff;
}

.bubble-time {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.68rem;
  color: var(--nf-muted);
}

.bubble.own .bubble-time {
  color: rgba(255, 255, 255, 0.72);
}

.empty-state {
  margin: auto;
  color: var(--nf-muted);
}

.thread-composer {
  display: flex;
  gap: 0.6rem;
  align-items: flex-end;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 0.75rem;
}

.thread-composer textarea {
  flex: 1;
  resize: none;
  padding: 0.6rem 0.8rem;
  border-radius: 10px;
  border: 1px solid var(--nf-line);
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
  line-height: 1.4;
}

.thread-composer textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.send-btn {
  border: none;
  border-radius: 999px;
  padding: 0.6rem 1.2rem;
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-weight: 700;
  cursor: pointer;
  flex-shrink: 0;
}

.send-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.thread-error {
  margin: 0;
  color: #f87171;
  font-size: 0.85rem;
}
</style>
