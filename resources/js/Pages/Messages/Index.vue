<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import StudentLayout from '../../Layouts/StudentLayout.vue';
import { timeAgo } from '../../lib/date';

defineOptions({ layout: StudentLayout });

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
  <div class="messages-page">
    <div class="messages-header">
      <h2>Messages</h2>
      <div class="messages-actions">
        <button type="button" class="btn-primary" @click="dmModalOpen = true">New message</button>
        <button type="button" class="btn-secondary" @click="groupModalOpen = true">New group</button>
      </div>
    </div>

    <div v-if="conversations.length" class="conversation-list">
      <Link
        v-for="conversation in conversations"
        :key="conversation.id"
        :href="route('conversations.show', conversation.id)"
        class="conversation-row"
      >
        <span class="avatar" :class="{ group: conversation.type === 'group' }">
          <img v-if="conversation.avatar_url" :src="conversation.avatar_url" alt="" />
          <span v-else>{{ conversation.display_name.slice(0, 1).toUpperCase() }}</span>
        </span>
        <div class="conversation-info">
          <span class="conversation-name">
            {{ conversation.display_name }}
            <span v-if="conversation.type === 'group'" class="member-count">
              {{ conversation.participant_count }} members
            </span>
          </span>
          <span v-if="conversation.last_message" class="conversation-preview">
            {{ conversation.last_message.is_own ? 'You: ' : '' }}{{ excerpt(conversation.last_message.content) }}
          </span>
          <span v-else class="conversation-preview empty">No messages yet</span>
        </div>
        <div class="conversation-meta">
          <span v-if="conversation.last_message" class="conversation-time">
            {{ timeAgo(conversation.last_message.created_at) }}
          </span>
          <span v-if="conversation.unread_count > 0" class="unread-pill">{{ conversation.unread_count }}</span>
        </div>
      </Link>
    </div>
    <p v-else class="empty">
      No conversations yet.
      <template v-if="friends.length">Start one with the buttons above.</template>
      <template v-else>Add some <Link href="/friends">friends</Link> first, then message them here.</template>
    </p>

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
          <p v-else class="empty">You need accepted friends before you can message anyone.</p>
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

            <p class="picker-hint">Pick at least two friends:</p>
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
            <p v-else class="empty">You need accepted friends before you can create a group.</p>
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
.messages-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.messages-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.messages-header h2 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--nf-ink);
}

.messages-actions {
  display: flex;
  gap: 0.5rem;
}

.conversation-list {
  display: flex;
  flex-direction: column;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  overflow: hidden;
}

.conversation-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  text-decoration: none;
  border-bottom: 1px solid var(--nf-line);
}

.conversation-row:last-child {
  border-bottom: none;
}

.conversation-row:hover {
  background: var(--nf-surface-2);
}

.avatar {
  width: 2.6rem;
  height: 2.6rem;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: var(--nf-surface-2);
  color: var(--nf-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.avatar.group {
  border-radius: 12px;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.conversation-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.conversation-name {
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--nf-ink);
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.member-count {
  font-weight: 500;
  font-size: 0.75rem;
  color: var(--nf-muted);
}

.conversation-preview {
  font-size: 0.82rem;
  color: var(--nf-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.conversation-preview.empty {
  font-style: italic;
}

.conversation-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.3rem;
  flex-shrink: 0;
}

.conversation-time {
  font-size: 0.75rem;
  color: var(--nf-muted);
}

.unread-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.2rem;
  height: 1.2rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-size: 0.7rem;
  font-weight: 700;
}

.empty {
  color: var(--nf-muted);
  font-size: 0.9rem;
}

.empty :deep(a),
.empty a {
  color: var(--nf-accent);
  font-weight: 600;
}

.btn-primary {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  white-space: nowrap;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary.full {
  width: 100%;
  margin-top: 0.75rem;
  padding: 0.65rem;
}

.btn-secondary {
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  border: 1px solid var(--nf-line);
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  white-space: nowrap;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 6vh 1rem;
  overflow-y: auto;
}

.modal {
  width: 100%;
  max-width: 440px;
  background: var(--nf-panel, #1f2027);
  border: 1px solid var(--nf-line, #2c2d36);
  border-radius: 14px;
  padding: 1rem;
  color: var(--nf-ink, #e9e9ee);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--nf-line, #2c2d36);
  margin-bottom: 0.9rem;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--nf-ink, #e9e9ee);
}

.modal-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  border: none;
  background: var(--nf-surface-2, #2a2b33);
  color: var(--nf-ink, #e9e9ee);
  cursor: pointer;
}

.friend-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  max-height: 320px;
  overflow-y: auto;
}

.friend-list li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.friend-list .avatar {
  width: 2.2rem;
  height: 2.2rem;
}

.friend-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.friend-name {
  font-weight: 600;
  font-size: 0.88rem;
  color: var(--nf-ink, #e9e9ee);
}

.friend-username {
  font-size: 0.75rem;
  color: var(--nf-muted, #9497a6);
}

.picker-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  cursor: pointer;
}

.picker-row input[type='checkbox'] {
  accent-color: var(--nf-accent, #0d9488);
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.group-name-input {
  width: 100%;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--nf-line, #2c2d36);
  border-radius: 10px;
  background: var(--nf-surface-2, #2a2b33);
  color: var(--nf-ink, #e9e9ee);
  font: inherit;
  margin-bottom: 0.75rem;
}

.group-name-input:focus {
  outline: none;
  border-color: var(--nf-accent, #0d9488);
}

.picker-hint {
  margin: 0 0 0.6rem;
  font-size: 0.8rem;
  color: var(--nf-muted, #9497a6);
}

.error {
  margin: 0.4rem 0 0;
  color: #f87171;
  font-size: 0.8rem;
}
</style>
