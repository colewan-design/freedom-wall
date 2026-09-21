<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import FeedLayout from '../../Layouts/FeedLayout.vue';

defineOptions({ layout: FeedLayout });

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
  <Head title="Network" />

  <div class="net-page">
    <section class="net-head">
      <div class="net-head-copy">
        <h1>Your network</h1>
        <p>Find classmates, answer requests, and keep track of who you are connected with.</p>
      </div>
      <form class="net-search" @submit.prevent="runSearch">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input v-model="search" type="search" placeholder="Search students by username..." aria-label="Search students by username" />
        <button type="submit">Search</button>
      </form>
    </section>

    <section v-if="searchResults.length || query" class="net-panel">
      <div class="net-panel-head">
        <h2>Search results</h2>
        <span v-if="searchResults.length" class="net-count">{{ searchResults.length }}</span>
      </div>
      <ul v-if="searchResults.length" class="person-list">
        <li v-for="person in searchResults" :key="person.id">
          <Link :href="`/profile/${person.username}`" class="avatar">
            <img v-if="person.avatar_url" :src="person.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(person.name) }}</span>
          </Link>
          <div class="person-info">
            <Link :href="`/profile/${person.username}`" class="name">{{ person.name }}</Link>
            <span class="username">@{{ person.username }}</span>
          </div>
          <button type="button" class="btn-primary" @click="sendRequest(person.username)">Connect</button>
        </li>
      </ul>
      <p v-else class="empty">No students found for “{{ query }}”.</p>
    </section>

    <section v-if="incoming.length" class="net-panel">
      <div class="net-panel-head">
        <h2>Requests</h2>
        <span class="net-count">{{ incoming.length }}</span>
      </div>
      <ul class="person-list">
        <li v-for="request in incoming" :key="request.id">
          <Link :href="`/profile/${request.requester.username}`" class="avatar">
            <img v-if="request.requester.avatar_url" :src="request.requester.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(request.requester.name) }}</span>
          </Link>
          <div class="person-info">
            <Link :href="`/profile/${request.requester.username}`" class="name">{{ request.requester.name }}</Link>
            <span class="username">@{{ request.requester.username }}</span>
          </div>
          <button type="button" class="btn-primary" @click="accept(request.id)">Accept</button>
          <button type="button" class="btn-secondary" @click="remove(request.id)">Decline</button>
        </li>
      </ul>
    </section>

    <section v-if="outgoing.length" class="net-panel">
      <div class="net-panel-head">
        <h2>Sent requests</h2>
        <span class="net-count">{{ outgoing.length }}</span>
      </div>
      <ul class="person-list">
        <li v-for="request in outgoing" :key="request.id">
          <Link :href="`/profile/${request.addressee.username}`" class="avatar">
            <img v-if="request.addressee.avatar_url" :src="request.addressee.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(request.addressee.name) }}</span>
          </Link>
          <div class="person-info">
            <Link :href="`/profile/${request.addressee.username}`" class="name">{{ request.addressee.name }}</Link>
            <span class="username">@{{ request.addressee.username }}</span>
          </div>
          <span class="pending-tag">Pending</span>
          <button type="button" class="btn-secondary" @click="remove(request.id)">Cancel</button>
        </li>
      </ul>
    </section>

    <section class="net-panel">
      <div class="net-panel-head">
        <h2>Your connections</h2>
        <span class="net-count">{{ friends.length }}</span>
      </div>
      <ul v-if="friends.length" class="person-list">
        <li v-for="friend in friends" :key="friend.friendship_id">
          <Link :href="`/profile/${friend.user.username}`" class="avatar">
            <img v-if="friend.user.avatar_url" :src="friend.user.avatar_url" alt="" />
            <span v-else>{{ avatarLetter(friend.user.name) }}</span>
          </Link>
          <div class="person-info">
            <Link :href="`/profile/${friend.user.username}`" class="name">{{ friend.user.name }}</Link>
            <span class="username">@{{ friend.user.username }}</span>
          </div>
          <Link :href="route('messages.index')" class="btn-secondary">Message</Link>
          <button type="button" class="btn-secondary" @click="remove(friend.friendship_id)">Remove</button>
        </li>
      </ul>
      <p v-else class="empty">No connections yet — search for a classmate above to get started.</p>
    </section>
  </div>
</template>

<style scoped>
.net-page {
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

.net-head { padding: 16px 18px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.net-head-copy h1 { margin: 0; font-size: 20px; letter-spacing: -.4px; }
.net-head-copy p { margin: 5px 0 0; color: var(--feed-muted); font-size: 12px; }
.net-search { margin-top: 13px; display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 5px 0 13px; border: 1px solid #d9e2dd; border-radius: 8px; background: #f6f8f7; }
.net-search:focus-within { border-color: #69a989; box-shadow: 0 0 0 3px rgba(7,91,50,.08); }
.net-search svg { width: 18px; flex: 0 0 18px; fill: none; stroke: #66726c; stroke-width: 1.8; stroke-linecap: round; }
.net-search input { min-width: 0; flex: 1; height: 100%; border: 0; outline: 0; background: transparent; color: var(--feed-ink); font: inherit; font-size: 12px; }
.net-search input::placeholder { color: #87928c; }
.net-search button { height: 30px; padding: 0 15px; border: 0; border-radius: 6px; background: linear-gradient(135deg, #08713e, #07562f); color: #fff; font: inherit; font-size: 11px; font-weight: 700; cursor: pointer; }

.net-panel { padding: 13px 16px 15px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; box-shadow: 0 2px 9px rgba(17,53,33,.035); }
.net-panel-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.net-panel-head h2 { margin: 0; font-size: 14px; }
.net-count { display: grid; place-items: center; min-width: 21px; height: 21px; padding: 0 6px; border-radius: 50px; background: #eaf5ef; color: var(--feed-green); font-size: 11px; font-weight: 700; }

.person-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.person-list li { display: flex; align-items: center; gap: 11px; padding: 9px 0; border-top: 1px solid #eef3f0; }
.person-list li:first-child { border-top: 0; padding-top: 2px; }
.avatar { width: 44px; height: 44px; flex: 0 0 44px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #e8f1ec; color: var(--feed-green); font-size: 17px; font-weight: 800; text-decoration: none; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.person-info { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.name { overflow: hidden; color: var(--feed-ink); font-size: 13px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
.name:hover { color: var(--feed-green); }
.username { color: var(--feed-muted); font-size: 11px; }
.pending-tag { padding: 4px 9px; border-radius: 50px; background: #f4f7f5; color: var(--feed-muted); font-size: 10px; font-weight: 600; }

.btn-primary, .btn-secondary { min-width: 84px; padding: 8px 12px; border-radius: 6px; font: inherit; font-size: 11px; font-weight: 700; text-align: center; text-decoration: none; cursor: pointer; white-space: nowrap; }
.btn-primary { border: 0; background: linear-gradient(135deg, #08713e, #07562f); color: #fff; }
.btn-secondary { border: 1px solid var(--feed-line); background: #fff; color: #3f4c45; }
.btn-secondary:hover { border-color: #a9c9b8; color: var(--feed-green); }

.empty { margin: 0; color: var(--feed-muted); font-size: 12px; }

@media (max-width: 760px) {
  .net-page { padding-top: 10px; }
  .net-search { flex-wrap: wrap; height: auto; padding: 6px; }
  .net-search input { flex-basis: 60%; height: 30px; }
  .person-list li { flex-wrap: wrap; }
  .person-info { flex-basis: 100%; order: -1; }
}
</style>
