<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import StudentLayout from '../../Layouts/StudentLayout.vue';

defineOptions({ layout: StudentLayout });

const props = defineProps({
  friends: Array,
  incoming: Array,
  outgoing: Array,
  searchResults: Array,
  query: String,
});

const search = ref(props.query || '');

function runSearch() {
  router.get(route('friends.index'), { q: search.value }, { preserveState: true, replace: true });
}

function sendRequest(username) {
  router.post(route('friends.store', username), {}, { preserveScroll: true, onSuccess: runSearch });
}

function accept(friendshipId) {
  router.post(route('friends.accept', friendshipId), {}, { preserveScroll: true });
}

function remove(friendshipId) {
  router.delete(route('friends.destroy', friendshipId), { preserveScroll: true });
}

function avatarLetter(name) {
  return name.slice(0, 1).toUpperCase();
}
</script>

<template>
  <div class="friends-page">
    <section class="panel">
      <h2>Find students</h2>
      <form class="search-form" @submit.prevent="runSearch">
        <input v-model="search" type="text" placeholder="Search by username…" />
        <button type="submit">Search</button>
      </form>

      <ul v-if="searchResults.length" class="person-list">
        <li v-for="person in searchResults" :key="person.id">
          <span class="avatar">
            <img v-if="person.avatar_url" :src="person.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(person.name) }}</span>
          </span>
          <div class="person-info">
            <span class="name">{{ person.name }}</span>
            <span class="username">@{{ person.username }}</span>
          </div>
          <button type="button" class="btn-primary" @click="sendRequest(person.username)">Add friend</button>
        </li>
      </ul>
      <p v-else-if="query" class="empty">No students found for "{{ query }}".</p>
    </section>

    <section v-if="incoming.length" class="panel">
      <h2>Friend requests</h2>
      <ul class="person-list">
        <li v-for="request in incoming" :key="request.id">
          <span class="avatar">
            <img v-if="request.requester.avatar_url" :src="request.requester.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(request.requester.name) }}</span>
          </span>
          <div class="person-info">
            <span class="name">{{ request.requester.name }}</span>
            <span class="username">@{{ request.requester.username }}</span>
          </div>
          <button type="button" class="btn-primary" @click="accept(request.id)">Accept</button>
          <button type="button" class="btn-secondary" @click="remove(request.id)">Decline</button>
        </li>
      </ul>
    </section>

    <section v-if="outgoing.length" class="panel">
      <h2>Sent requests</h2>
      <ul class="person-list">
        <li v-for="request in outgoing" :key="request.id">
          <span class="avatar">
            <img v-if="request.addressee.avatar_url" :src="request.addressee.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(request.addressee.name) }}</span>
          </span>
          <div class="person-info">
            <span class="name">{{ request.addressee.name }}</span>
            <span class="username">@{{ request.addressee.username }}</span>
          </div>
          <button type="button" class="btn-secondary" @click="remove(request.id)">Cancel</button>
        </li>
      </ul>
    </section>

    <section class="panel">
      <h2>Your friends ({{ friends.length }})</h2>
      <ul v-if="friends.length" class="person-list">
        <li v-for="friend in friends" :key="friend.friendship_id">
          <span class="avatar">
            <img v-if="friend.user.avatar_url" :src="friend.user.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(friend.user.name) }}</span>
          </span>
          <div class="person-info">
            <span class="name">{{ friend.user.name }}</span>
            <span class="username">@{{ friend.user.username }}</span>
          </div>
          <button type="button" class="btn-secondary" @click="remove(friend.friendship_id)">Unfriend</button>
        </li>
      </ul>
      <p v-else class="empty">No friends yet — search for a username above to get started.</p>
    </section>
  </div>
</template>

<style scoped>
.friends-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.panel {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 1.25rem;
}

.panel h2 {
  margin: 0 0 1rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--nf-ink);
}

.search-form {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.search-form input {
  flex: 1;
  padding: 0.6rem 0.8rem;
  border: 1px solid var(--nf-line);
  border-radius: 8px;
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
}

.search-form button {
  background: var(--nf-accent);
  color: #fff;
  border: none;
  padding: 0 1.1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.person-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.person-list li {
  display: flex;
  align-items: center;
  gap: 0.7rem;
}

.avatar {
  width: 2.4rem;
  height: 2.4rem;
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

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.person-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.name {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--nf-ink);
}

.username {
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.btn-primary {
  background: var(--nf-accent);
  color: #fff;
  border: none;
  padding: 0.45rem 0.9rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  white-space: nowrap;
}

.btn-secondary {
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  border: 1px solid var(--nf-line);
  padding: 0.45rem 0.9rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  white-space: nowrap;
}

.empty {
  color: var(--nf-muted);
  font-size: 0.88rem;
  margin: 0;
}
</style>
