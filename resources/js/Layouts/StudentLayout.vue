<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const page = usePage();

const authUser = computed(() => page.props.auth?.user ?? null);
const pendingRequestCount = computed(() => page.props.pendingRequestCount ?? 0);
const unreadMessageCount = computed(() => page.props.unreadMessageCount ?? 0);
const storyFriends = computed(() => page.props.storyFriends ?? []);
const suggestedFriends = computed(() => page.props.suggestedFriends ?? []);
const requestedIds = ref([]);

function followSuggestion(user) {
  requestedIds.value.push(user.id);
  router.post(route('friends.store', user.username), {}, { preserveScroll: true });
}

const isActive = (prefix) => computed(() => page.url.startsWith(prefix));

const theme = ref('dark');

onMounted(() => {
  const saved = localStorage.getItem('wall-theme');
  if (saved === 'light' || saved === 'dark') theme.value = saved;
});

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('wall-theme', theme.value);
}

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <div class="sl-shell" :class="theme">
    <div class="sl-grid">
      <aside class="sl-sidebar sl-left">
        <div class="sl-profile-card">
          <span class="sl-profile-avatar-wrap">
            <span class="sl-profile-splash"></span>
            <span class="sl-avatar sl-avatar-lg">
              <img v-if="authUser?.avatar_url" :src="authUser.avatar_url" alt="" />
              <span v-else>{{ authUser?.name?.slice(0, 1).toUpperCase() }}</span>
            </span>
          </span>
          <div class="sl-profile-info">
            <span class="sl-profile-name">{{ authUser?.name }}</span>
            <span class="sl-profile-username">@{{ authUser?.username }}</span>
          </div>
        </div>

        <nav class="sl-nav">
          <Link href="/feed" class="sl-nav-item" :class="{ active: isActive('/feed').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 11.5 12 4l8 7.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M6 10v9h12v-9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            News Feed
          </Link>

          <Link href="/messages" class="sl-nav-item" :class="{ active: isActive('/messages').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3" y="5" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7" />
              <path d="m4 7 8 6 8-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Messages
            <span v-if="unreadMessageCount > 0" class="sl-badge">{{ unreadMessageCount }}</span>
          </Link>

          <Link href="/wall" class="sl-nav-item" :class="{ active: isActive('/wall').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M4 5.5h16v9H9l-4 3.5v-3.5H4v-9Z"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linejoin="round"
              />
            </svg>
            Forums
          </Link>

          <Link href="/friends" class="sl-nav-item" :class="{ active: isActive('/friends').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7" />
              <path d="M3 19c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
              <circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.7" />
              <path d="M15.5 19c.2-2.2 1.8-3.7 4-3.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            Friends
            <span v-if="pendingRequestCount > 0" class="sl-badge">{{ pendingRequestCount }}</span>
          </Link>

          <span class="sl-nav-item inert" title="Coming soon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3.5" y="4" width="17" height="16" rx="2.5" stroke="currentColor" stroke-width="1.7" />
              <circle cx="8.5" cy="9.5" r="1.6" stroke="currentColor" stroke-width="1.7" />
              <path d="m5 17 4.5-5 3.5 3.5 2.5-2.5 3.5 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Media
          </span>

          <Link href="/journal" class="sl-nav-item" :class="{ active: isActive('/journal').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M5 4.5h11.5A2.5 2.5 0 0 1 19 7v13H7.5A2.5 2.5 0 0 1 5 17.5v-13Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
              <path d="M5 17.5A2.5 2.5 0 0 1 7.5 15H19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            Journal
          </Link>

          <Link href="/saved" class="sl-nav-item" :class="{ active: isActive('/saved').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
            Saved
          </Link>

          <Link href="/settings/profile" class="sl-nav-item" :class="{ active: isActive('/settings').value }">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
              <path
                d="M19 12a7 7 0 0 0-.1-1.2l1.9-1.5-2-3.4-2.2.9a7 7 0 0 0-2-1.2L14.3 3H9.7l-.3 2.6a7 7 0 0 0-2 1.2l-2.2-.9-2 3.4L5 10.8a7 7 0 0 0 0 2.4l-1.9 1.5 2 3.4 2.2-.9a7 7 0 0 0 2 1.2l.3 2.6h4.6l.3-2.6a7 7 0 0 0 2-1.2l2.2.9 2-3.4-1.9-1.5c.07-.4.1-.8.1-1.2Z"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linejoin="round"
              />
            </svg>
            Settings
          </Link>
        </nav>

        <div class="sl-sidebar-footer">
          <button
            type="button"
            class="sl-icon-btn"
            :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
            @click="toggleTheme"
          >
            <svg v-if="theme === 'dark'" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6" />
              <path
                d="M12 2.5v2M12 19.5v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2.5 12h2M19.5 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
              />
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linejoin="round"
              />
            </svg>
          </button>
          <button type="button" class="sl-logout-btn" @click="logout">Log out</button>
        </div>
      </aside>

      <main class="sl-main">
        <slot />
      </main>

      <aside class="sl-sidebar sl-right">
        <div v-if="storyFriends.length" class="sl-panel">
          <h2>Stories</h2>
          <div class="sl-stories">
            <Link
              v-for="friend in storyFriends"
              :key="friend.id"
              :href="`/profile/${friend.username}`"
              class="sl-story-card"
              :style="friend.avatar_url ? { backgroundImage: `url(${friend.avatar_url})` } : null"
            >
              <span v-if="!friend.avatar_url" class="sl-story-fallback">{{ friend.name.slice(0, 1).toUpperCase() }}</span>
              <span class="sl-story-overlay">
                <span class="sl-story-avatar">
                  <img v-if="friend.avatar_url" :src="friend.avatar_url" alt="" />
                  <span v-else>{{ friend.name.slice(0, 1).toUpperCase() }}</span>
                </span>
                <span class="sl-story-name">{{ friend.name }}</span>
              </span>
            </Link>
          </div>
        </div>

        <div class="sl-panel">
          <h2>Suggestions</h2>
          <ul v-if="suggestedFriends.length" class="sl-suggestion-list">
            <li v-for="person in suggestedFriends" :key="person.id">
              <span class="sl-avatar sl-avatar-sm">
                <img v-if="person.avatar_url" :src="person.avatar_url" alt="" />
                <span v-else>{{ person.name.slice(0, 1).toUpperCase() }}</span>
              </span>
              <span class="sl-suggestion-name">{{ person.name }}</span>
              <button
                type="button"
                class="sl-follow-btn"
                :disabled="requestedIds.includes(person.id)"
                @click="followSuggestion(person)"
              >
                {{ requestedIds.includes(person.id) ? 'Requested' : 'Add Friend' }}
              </button>
            </li>
          </ul>
          <ul v-else class="sl-suggestion-list">
            <li v-for="n in 3" :key="n" class="placeholder">
              <span class="sl-avatar sl-avatar-sm"></span>
              <span class="sl-suggestion-name placeholder-text">Add Friend</span>
              <button type="button" class="sl-follow-btn" disabled>Add Friend</button>
            </li>
          </ul>
        </div>

        <div class="sl-panel">
          <h2>Recommendations</h2>
          <div class="sl-tag-grid">
            <span v-for="tag in ['UI/UX', 'Music', 'Cooking', 'Hiking']" :key="tag" class="sl-rec-tag">{{ tag }}</span>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.sl-shell.dark {
  --nf-bg: #17181d;
  --nf-panel: #1f2027;
  --nf-line: #2c2d36;
  --nf-ink: #e9e9ee;
  --nf-muted: #9497a6;
  --nf-accent: #0d9488;
  --nf-accent-contrast: #ffffff;
  --nf-surface-2: #2a2b33;
  --post-tint-blue: #1b2430;
  --post-tint-cream: #2a2620;
  --nf-nav-active-bg: var(--nf-accent);
  --nf-nav-active-fg: var(--nf-accent-contrast);
}

.sl-shell.light {
  --nf-bg: #ffffff;
  --nf-panel: #ffffff;
  --nf-line: #e7e8ec;
  --nf-ink: #16181d;
  --nf-muted: #6b7280;
  --nf-accent: #0d9488;
  --nf-accent-contrast: #ffffff;
  --nf-surface-2: #f1f1f4;
  --post-tint-blue: #eaf2ff;
  --post-tint-cream: #fff8e6;
  --nf-nav-active-bg: #14151a;
  --nf-nav-active-fg: #ffffff;
}

.sl-shell {
  width: 100%;
  min-height: 100vh;
  background: var(--nf-bg);
  color: var(--nf-ink);
  transition: background 0.2s ease, color 0.2s ease;
}

.sl-grid {
  display: grid;
  grid-template-columns: 260px minmax(0, 1fr) 280px;
  gap: 1.25rem;
  max-width: 1440px;
  margin: 0 auto;
  padding: 1.5rem;
  align-items: start;
}

.sl-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  position: sticky;
  top: 1.5rem;
}

.sl-right {
  gap: 2rem;
}

.sl-profile-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
  text-align: center;
  padding: 0.5rem 0 1rem;
}

.sl-profile-avatar-wrap {
  position: relative;
  width: 5rem;
  height: 5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.sl-profile-splash {
  position: absolute;
  top: -0.4rem;
  left: -1.1rem;
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: conic-gradient(from 120deg, #a78bfa, #f472b6, #fb923c, #60a5fa, #a78bfa);
  filter: blur(1px);
  opacity: 0.9;
}

.sl-avatar {
  width: 2.75rem;
  height: 2.75rem;
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

.sl-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sl-avatar-sm {
  width: 2.1rem;
  height: 2.1rem;
}

.sl-avatar-lg {
  position: relative;
  z-index: 1;
  width: 4rem;
  height: 4rem;
  font-size: 1.5rem;
  border: 3px solid var(--nf-bg);
}

.sl-profile-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 0;
}

.sl-profile-name {
  font-weight: 700;
  font-size: 1rem;
  color: var(--nf-ink);
}

.sl-profile-username {
  font-size: 0.8rem;
  color: var(--nf-muted);
}

.sl-nav {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.sl-nav-item {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.6rem 0.75rem;
  border-radius: 10px;
  color: var(--nf-muted);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.88rem;
}

.sl-nav-item:not(.inert):hover {
  background: var(--nf-surface-2);
  color: var(--nf-ink);
}

.sl-nav-item.active {
  background: var(--nf-nav-active-bg);
  color: var(--nf-nav-active-fg);
}

.sl-nav-item.inert {
  cursor: default;
  opacity: 0.55;
}

.sl-badge {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.2rem;
  height: 1.2rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.9);
  color: var(--nf-nav-active-bg);
  font-size: 0.7rem;
  font-weight: 700;
}

.sl-nav-item:not(.active) .sl-badge {
  background: var(--nf-nav-active-bg);
  color: var(--nf-nav-active-fg);
}

.sl-sidebar-footer {
  display: flex;
  gap: 0.5rem;
}

.sl-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.4rem;
  height: 2.4rem;
  flex-shrink: 0;
  border-radius: 10px;
  border: 1px solid var(--nf-line);
  background: var(--nf-panel);
  color: var(--nf-muted);
  cursor: pointer;
}

.sl-logout-btn {
  flex: 1;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  color: var(--nf-ink);
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.5rem 0.9rem;
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.sl-logout-btn:hover {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.sl-main {
  min-width: 0;
}

.sl-panel h2 {
  margin: 0 0 0.85rem;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--nf-ink);
}

.sl-suggestion-list li.placeholder {
  opacity: 0.5;
}

.placeholder-text {
  color: var(--nf-muted);
}

.sl-stories {
  display: flex;
  gap: 0.6rem;
  overflow-x: auto;
}

.sl-story-card {
  position: relative;
  flex: 0 0 auto;
  width: 6.5rem;
  height: 8.5rem;
  border-radius: 16px;
  background-color: var(--nf-surface-2);
  background-size: cover;
  background-position: center;
  overflow: hidden;
  text-decoration: none;
  display: block;
}

.sl-story-card::after {
  content: '';
  position: absolute;
  inset: auto 0 0 0;
  height: 60%;
  background: linear-gradient(0deg, rgba(0, 0, 0, 0.75) 0%, rgba(0, 0, 0, 0) 100%);
}

.sl-story-fallback {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 700;
  color: var(--nf-accent);
}

.sl-story-overlay {
  position: absolute;
  left: 0.5rem;
  right: 0.5rem;
  bottom: 0.5rem;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.sl-story-avatar {
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  border: 2px solid #fff;
  background: var(--nf-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--nf-accent);
}

.sl-story-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sl-story-name {
  min-width: 0;
  color: #fff;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
}

.sl-suggestion-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.sl-suggestion-list li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.sl-suggestion-name {
  flex: 1;
  font-size: 0.85rem;
  color: var(--nf-muted);
}

.sl-follow-btn {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  border: 1px solid var(--nf-accent);
  border-radius: 999px;
  padding: 0.3rem 0.8rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
}

.sl-follow-btn:disabled {
  background: var(--nf-surface-2);
  color: var(--nf-muted);
  border-color: var(--nf-line);
  cursor: default;
}

.sl-tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.sl-rec-tag {
  background: var(--nf-surface-2);
  border: 1px solid var(--nf-line);
  color: var(--nf-muted);
  border-radius: 999px;
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 600;
}

@media (max-width: 1100px) {
  .sl-grid {
    grid-template-columns: 1fr;
  }

  .sl-sidebar {
    position: static;
  }

  .sl-left {
    order: 1;
  }

  .sl-main {
    order: 2;
  }

  .sl-right {
    order: 3;
  }

  .sl-nav {
    flex-direction: row;
    flex-wrap: wrap;
  }

  .sl-nav-item {
    flex: 1 1 auto;
    justify-content: center;
  }
}
</style>
