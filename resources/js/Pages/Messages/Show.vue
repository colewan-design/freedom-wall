<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FeedLayout from '../../Layouts/FeedLayout.vue';
import { timeAgo } from '../../lib/date';

defineOptions({ layout: FeedLayout });

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
  <Head :title="conversation.display_name" />

  <section class="thread-page">
    <header class="thread-header">
      <Link :href="route('messages.index')" class="back-btn" aria-label="Back to messages">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5 8 12l7 7" /></svg>
      </Link>
      <span class="thread-avatar" :class="{ group: isGroup }">
        <img v-if="conversation.avatar_url" :src="conversation.avatar_url" alt="" />
        <span v-else>{{ conversation.display_name.slice(0, 1).toUpperCase() }}</span>
      </span>
      <div class="thread-title">
        <h1>{{ conversation.display_name }}</h1>
        <span v-if="isGroup">{{ conversation.participants.length }} members</span>
        <span v-else>Direct message</span>
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
  --feed-green: #075b32;
  --feed-ink: #17211b;
  --feed-muted: #6f7d75;
  --feed-line: #dce5e0;
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding-top: 14px;
  color: var(--feed-ink);
}

.thread-header { display: flex; align-items: center; gap: 11px; padding: 11px 16px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.back-btn { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%; background: #f2f6f4; color: #3f4c45; }
.back-btn:hover { background: #e8f1ec; color: var(--feed-green); }
.back-btn svg { width: 17px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.thread-avatar { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #e8f1ec; color: var(--feed-green); font-size: 17px; font-weight: 800; }
.thread-avatar.group { border-radius: 11px; }
.thread-avatar img { width: 100%; height: 100%; object-fit: cover; }
.thread-title { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.thread-title h1 { margin: 0; overflow: hidden; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
.thread-title span { margin-top: 2px; color: var(--feed-muted); font-size: 11px; }
.leave-btn { padding: 8px 14px; border: 1px solid var(--feed-line); border-radius: 6px; background: #fff; color: #3f4c45; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.leave-btn:hover { border-color: #e8b4b0; color: #b42318; }

.thread-stream { height: min(60vh, 560px); padding: 16px; display: flex; flex-direction: column; align-items: flex-start; gap: 8px; overflow-y: auto; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.bubble { max-width: min(520px, 84%); padding: 9px 13px; border: 1px solid #e6ede9; border-radius: 12px; background: #f4f8f6; }
.bubble.own { align-self: flex-end; border-color: transparent; background: linear-gradient(135deg, #08713e, #07562f); }
.bubble-sender { margin-bottom: 3px; color: var(--feed-green); font-size: 10px; font-weight: 800; }
.bubble p { margin: 0; color: var(--feed-ink); font-size: 12px; line-height: 1.5; white-space: pre-wrap; }
.bubble.own p { color: #fff; }
.bubble-time { display: block; margin-top: 4px; color: #87928c; font-size: 9px; }
.bubble.own .bubble-time { color: rgba(255,255,255,.72); }
.empty-state { margin: auto; color: var(--feed-muted); font-size: 12px; }

.thread-composer { display: flex; align-items: flex-end; gap: 9px; padding: 11px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.thread-composer textarea { flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid #d9e2dd; border-radius: 8px; outline: none; resize: none; background: #f6f8f7; color: var(--feed-ink); font: inherit; font-size: 12px; line-height: 1.45; }
.thread-composer textarea::placeholder { color: #87928c; }
.thread-composer textarea:focus { border-color: #69a989; box-shadow: 0 0 0 3px rgba(7,91,50,.08); }
.send-btn { flex-shrink: 0; min-width: 84px; padding: 11px 18px; border: 0; border-radius: 6px; background: linear-gradient(135deg, #08713e, #07562f); color: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }
.send-btn:disabled { opacity: .45; cursor: default; }
.thread-error { margin: 0; color: #b42318; font-size: 11px; }

@media (max-width: 760px) {
  .thread-page { padding-top: 10px; }
  .thread-avatar { display: none; }
  .thread-stream { height: 58vh; padding: 12px; }
  .bubble { max-width: 90%; }
}
</style>
