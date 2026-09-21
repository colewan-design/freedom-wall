<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FeedLayout from '../../Layouts/FeedLayout.vue';
import { timeAgo } from '../../lib/date';

defineOptions({ layout: FeedLayout });

defineProps({
  conversations: Array,
  friends: Array,
});

const dmModalOpen = ref(false);
const groupModalOpen = ref(false);

const groupForm = useForm({
  type: 'group',
  name: '',
  participants: [],
});

function startDm(username) {
  dmModalOpen.value = false;
  router.post(route('conversations.store'), { type: 'direct', username });
}

function toggleGroupMember(id) {
  const index = groupForm.participants.indexOf(id);
  if (index === -1) {
    groupForm.participants.push(id);
  } else {
    groupForm.participants.splice(index, 1);
  }
}

function createGroup() {
  groupForm.post(route('conversations.store'), {
    onSuccess: () => {
      groupForm.reset();
      groupModalOpen.value = false;
    },
  });
}

function excerpt(text, length = 46) {
  if (!text) return '';
  return text.length > length ? `${text.slice(0, length).trim()}…` : text;
}
</script>

<template>
  <Head title="Messages" />

  <div class="msg-page">
    <section class="msg-head">
      <div class="msg-head-copy">
        <h1>Messages</h1>
        <p>Your direct chats and group threads with classmates.</p>
      </div>
      <div class="msg-head-actions">
        <button type="button" class="btn-primary" @click="dmModalOpen = true">New message</button>
        <button type="button" class="btn-secondary" @click="groupModalOpen = true">New group</button>
      </div>
    </section>

    <section v-if="conversations.length" class="conversation-list">
      <Link
        v-for="conversation in conversations"
        :key="conversation.id"
        :href="route('conversations.show', conversation.id)"
        class="conversation-row"
        :class="{ unread: conversation.unread_count > 0 }"
      >
        <span class="avatar" :class="{ group: conversation.type === 'group' }">
          <img v-if="conversation.avatar_url" :src="conversation.avatar_url" alt="" />
          <span v-else>{{ conversation.display_name.slice(0, 1).toUpperCase() }}</span>
        </span>
        <span class="conversation-info">
          <span class="conversation-name">
            {{ conversation.display_name }}
            <em v-if="conversation.type === 'group'">{{ conversation.participant_count }} members</em>
          </span>
          <span v-if="conversation.last_message" class="conversation-preview">
            {{ conversation.last_message.is_own ? 'You: ' : '' }}{{ excerpt(conversation.last_message.content) }}
          </span>
          <span v-else class="conversation-preview muted">No messages yet</span>
        </span>
        <span class="conversation-meta">
          <span v-if="conversation.last_message" class="conversation-time">
            {{ timeAgo(conversation.last_message.created_at) }}
          </span>
          <span v-if="conversation.unread_count > 0" class="unread-pill">{{ conversation.unread_count }}</span>
        </span>
      </Link>
    </section>

    <section v-else class="msg-empty">
      <span>✉</span>
      <h2>No conversations yet</h2>
      <p v-if="friends.length">Start one with the buttons above and your threads will show up here.</p>
      <p v-else>Connect with classmates on your <Link href="/friends">network</Link> first, then message them here.</p>
    </section>

    <Teleport to="body">
      <div v-if="dmModalOpen" class="modal-overlay" @click.self="dmModalOpen = false">
        <div class="modal">
          <div class="modal-header">
            <h3>New message</h3>
            <button type="button" class="modal-close" aria-label="Close" @click="dmModalOpen = false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </button>
          </div>
          <ul v-if="friends.length" class="friend-list">
            <li v-for="friend in friends" :key="friend.id">
              <span class="avatar">
                <img v-if="friend.avatar_url" :src="friend.avatar_url" alt="" />
                <span v-else>{{ friend.name.slice(0, 1).toUpperCase() }}</span>
              </span>
              <div class="friend-info">
                <span class="friend-name">{{ friend.name }}</span>
                <span class="friend-username">@{{ friend.username }}</span>
              </div>
              <button type="button" class="btn-primary" @click="startDm(friend.username)">Message</button>
            </li>
          </ul>
          <p v-else class="modal-empty">You need accepted connections before you can message anyone.</p>
        </div>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="groupModalOpen" class="modal-overlay" @click.self="groupModalOpen = false">
        <div class="modal">
          <div class="modal-header">
            <h3>New group</h3>
            <button type="button" class="modal-close" aria-label="Close" @click="groupModalOpen = false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </button>
          </div>

          <form @submit.prevent="createGroup">
            <input v-model="groupForm.name" type="text" class="group-name-input" placeholder="Group name" />
            <p v-if="groupForm.errors.name" class="error">{{ groupForm.errors.name }}</p>

            <p class="picker-hint">Pick at least two classmates:</p>
            <ul v-if="friends.length" class="friend-list picker">
              <li v-for="friend in friends" :key="friend.id">
                <label class="picker-row">
                  <input
                    type="checkbox"
                    :checked="groupForm.participants.includes(friend.id)"
                    @change="toggleGroupMember(friend.id)"
                  />
                  <span class="avatar">
                    <img v-if="friend.avatar_url" :src="friend.avatar_url" alt="" />
                    <span v-else>{{ friend.name.slice(0, 1).toUpperCase() }}</span>
                  </span>
                  <div class="friend-info">
                    <span class="friend-name">{{ friend.name }}</span>
                    <span class="friend-username">@{{ friend.username }}</span>
                  </div>
                </label>
              </li>
            </ul>
            <p v-else class="modal-empty">You need accepted connections before you can create a group.</p>
            <p v-if="groupForm.errors.participants" class="error">{{ groupForm.errors.participants }}</p>

            <button
              type="submit"
              class="btn-primary full"
              :disabled="groupForm.processing || !groupForm.name.trim() || groupForm.participants.length < 2"
            >
              {{ groupForm.processing ? 'Creating…' : 'Create group' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.msg-page {
  --feed-green: #075b32;
  --feed-ink: #17211b;
  --feed-muted: #6f7d75;
  --feed-line: #dce5e0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 14px;
  color: var(--feed-ink);
}

.msg-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 15px 18px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.msg-head-copy h1 { margin: 0; font-size: 20px; letter-spacing: -.4px; }
.msg-head-copy p { margin: 5px 0 0; color: var(--feed-muted); font-size: 12px; }
.msg-head-actions { display: flex; gap: 8px; flex-shrink: 0; }

.conversation-list { overflow: hidden; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.conversation-row { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #eef3f0; text-decoration: none; transition: background .15s ease; }
.conversation-row:first-child { border-top: 0; }
.conversation-row:hover { background: #f4f8f6; }
.conversation-row.unread { background: #f7fbf9; }
.avatar { width: 46px; height: 46px; flex: 0 0 46px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #e8f1ec; color: var(--feed-green); font-size: 18px; font-weight: 800; }
.avatar.group { border-radius: 12px; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.conversation-info { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 3px; }
.conversation-name { display: flex; align-items: baseline; gap: 8px; overflow: hidden; color: var(--feed-ink); font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.conversation-name em { color: var(--feed-muted); font-size: 10px; font-style: normal; font-weight: 500; }
.conversation-preview { overflow: hidden; color: var(--feed-muted); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.conversation-row.unread .conversation-preview { color: #2b3a32; font-weight: 600; }
.conversation-preview.muted { font-style: italic; }
.conversation-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.conversation-time { color: #87928c; font-size: 10px; }
.unread-pill { display: grid; place-items: center; min-width: 19px; height: 19px; padding: 0 6px; border-radius: 50px; background: var(--feed-green); color: #fff; font-size: 10px; font-weight: 700; }

.msg-empty { padding: 48px 25px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; text-align: center; }
.msg-empty > span { color: var(--feed-green); font-size: 28px; }
.msg-empty h2 { margin: 10px 0 5px; font-size: 17px; }
.msg-empty p { max-width: 420px; margin: 0 auto; color: var(--feed-muted); font-size: 12px; }
.msg-empty a { color: var(--feed-green); font-weight: 700; }

.btn-primary, .btn-secondary { padding: 9px 15px; border-radius: 6px; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; white-space: nowrap; }
.btn-primary { border: 0; background: linear-gradient(135deg, #08713e, #07562f); color: #fff; }
.btn-primary:disabled { opacity: .45; cursor: default; }
.btn-primary.full { width: 100%; margin-top: 12px; padding: 11px; }
.btn-secondary { border: 1px solid var(--feed-line); background: #fff; color: #3f4c45; }
.btn-secondary:hover { border-color: #a9c9b8; color: var(--feed-green); }

.modal-overlay { position: fixed; inset: 0; z-index: 1000; display: flex; align-items: flex-start; justify-content: center; padding: 6vh 1rem; overflow-y: auto; background: rgba(5, 26, 16, .52); }
.modal { width: 100%; max-width: 440px; padding: 16px; border: 1px solid #dce5e0; border-radius: 12px; background: #fff; color: #17211b; font-family: Inter, var(--b-sans); box-shadow: 0 18px 44px rgba(6, 34, 20, .22); }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding-bottom: 11px; border-bottom: 1px solid #eef3f0; }
.modal-header h3 { margin: 0; font-size: 16px; font-weight: 800; }
.modal-close { display: grid; place-items: center; width: 30px; height: 30px; border: 0; border-radius: 50%; background: #f2f6f4; color: #3f4c45; cursor: pointer; }
.modal-close:hover { background: #e8f1ec; color: #075b32; }

.friend-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; max-height: 320px; overflow-y: auto; }
.friend-list li { display: flex; align-items: center; gap: 10px; }
.friend-list .avatar { width: 38px; height: 38px; flex-basis: 38px; font-size: 15px; }
.friend-info { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.friend-name { overflow: hidden; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.friend-username { color: #7b8781; font-size: 10px; }
.picker-row { display: flex; align-items: center; gap: 10px; width: 100%; cursor: pointer; }
.picker-row input[type='checkbox'] { width: 15px; height: 15px; flex-shrink: 0; accent-color: #075b32; }
.group-name-input { width: 100%; margin-bottom: 11px; padding: 10px 12px; border: 1px solid #d9e2dd; border-radius: 7px; background: #f6f8f7; color: #17211b; font: inherit; font-size: 12px; }
.group-name-input:focus { outline: none; border-color: #69a989; box-shadow: 0 0 0 3px rgba(7,91,50,.08); }
.picker-hint { margin: 0 0 9px; color: #7b8781; font-size: 11px; }
.modal-empty { margin: 0; color: #7b8781; font-size: 12px; }
.error { margin: 6px 0 0; color: #b42318; font-size: 11px; }

@media (max-width: 760px) {
  .msg-page { padding-top: 10px; }
  .msg-head { flex-direction: column; align-items: flex-start; }
  .conversation-row { padding: 11px 12px; }
}
</style>
