<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, provide, ref, watchEffect } from 'vue';

const page = usePage();
const isActive = (path) => computed(() => page.url === path || page.url.startsWith(`${path}?`));
const isChatPage = computed(() => page.component === 'Chat');
const isWallPage = computed(() => page.component === 'Wall');
const isAdminPage = computed(() => page.component?.startsWith('Admin/'));
const isFocusPage = computed(() => page.component === 'IdCheck/Index');
const authUser = computed(() => page.props.auth?.user ?? null);
const isStudentAuthed = computed(() => authUser.value?.role === 'student');

function logout() {
  router.post(route('admin.logout'));
}

const search = ref('');
const searchInput = ref(null);
provide('wallSearch', search);

const theme = ref('dark');

onMounted(() => {
  const saved = localStorage.getItem('wall-theme');
  if (saved === 'light' || saved === 'dark') theme.value = saved;
  window.addEventListener('keydown', focusSearchWithSlash);
});

onBeforeUnmount(() => window.removeEventListener('keydown', focusSearchWithSlash));

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('wall-theme', theme.value);
}

// Mirror the theme onto <html>: the palette tokens live at :root in app.css,
// so overlays teleported to <body> resolve the same values as in-shell content.
watchEffect(() => {
  if (typeof document === 'undefined') return;
  document.documentElement.setAttribute('data-theme', theme.value);
});

const posts = computed(() => page.props.posts ?? []);
const chatNickname = computed(() => page.props.chatNickname ?? 'Anonymous');
const chatStats = computed(() => page.props.chatStats ?? { totalMessages: 0, messagesToday: 0, pollLabel: 'Every 4 sec' });
const recentChatNicknames = computed(() => page.props.recentChatNicknames ?? []);

// The wall ships real totals as a `stats` prop because `posts` only holds the
// rendered slice. Pages without it (the student feed) still count what's loaded.
const wallStats = computed(() => page.props.stats ?? null);
const totalPosts = computed(() => wallStats.value?.total ?? posts.value.length);
const withPhotos = computed(
  () => wallStats.value?.withPhotos ?? posts.value.filter((p) => p.image_urls?.length).length,
);
const textOnly = computed(() => wallStats.value?.textOnly ?? posts.value.length - withPhotos.value);
const highlights = computed(() => posts.value.slice(0, 3));

const TAGS = ['confession', 'crush', 'exam', 'campus', 'org', 'rant'];

const RULES = [
  ['Be respectful', 'No hate, harassment, or personal attacks.'],
  ["Don't share identifying info", "Protect yourself and others' privacy."],
  ['Keep it real', 'Post genuine thoughts, experiences, and opinions.'],
  ['Every post is reviewed', "All submissions are checked before it's live."],
];

function focusSearchWithSlash(event) {
  if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
  event.preventDefault();
  searchInput.value?.focus();
}

function applyTag(tag) {
  search.value = search.value === tag ? '' : tag;
}

// Posts carry their category as a trailing hashtag (see Submission::CATEGORIES).
function postCategory(post) {
  return post.content?.match(/#([\w-]+)\s*$/i)?.[1] ?? 'community';
}

function requestComposer() {
  document.querySelector('#composer')?.click();
}

function excerpt(text, length = 60) {
  if (!text) return '';
  const clean = text.replace(/\s*#[\w-]+\s*$/i, '').trim();
  return clean.length > length ? `${clean.slice(0, length).trim()}...` : clean;
}
</script>

<template>
  <div class="nf-shell" :class="[theme, 'bryl']">
    <header class="nf-topbar">
      <span class="nf-brand">
        <img src="/images/branding/bsufw-mark-64.png" alt="BSU Freedom Wall" class="nf-brand-mark" />
        BSU Freedom Wall
      </span>

      <nav v-if="!isAdminPage" class="nf-tabs">
        <Link href="/wall" :class="{ active: isActive('/wall').value }">News Feed</Link>
        <Link href="/chat" :class="{ active: isActive('/chat').value }">Chat</Link>
        <Link href="/id-check" :class="{ active: isActive('/id-check').value }">ID Check</Link>
      </nav>
      <span v-else class="nf-admin-label">Moderation Dashboard</span>

      <div class="nf-user">
        <button
          type="button"
          class="nf-icon-btn"
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
        <Link v-if="isStudentAuthed" href="/feed" class="nf-login-btn">{{ authUser.name }}</Link>
        <Link v-else-if="!isAdminPage" href="/login" class="nf-login-btn">Log in</Link>
        <button v-else type="button" class="nf-logout-btn" @click="logout">Log out</button>
      </div>
    </header>

    <div class="nf-body" :class="{ 'admin-mode': isAdminPage, 'focus-mode': isFocusPage }">
      <aside v-if="!isAdminPage && !isFocusPage" class="nf-sidebar nf-left">
        <label v-if="!isChatPage" class="nf-search">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" />
            <path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input ref="searchInput" v-model="search" type="text" placeholder="Search posts, tags, or topics..." />
          <kbd>/</kbd>
        </label>

        <div v-if="isChatPage" class="nf-panel">
          <h2>Chat Snapshot</h2>
          <ul class="nf-stat-list">
            <li><span>Your nickname</span><strong>{{ chatNickname }}</strong></li>
            <li><span>Total messages</span><strong>{{ chatStats.totalMessages }}</strong></li>
            <li><span>Messages today</span><strong>{{ chatStats.messagesToday }}</strong></li>
            <li><span>Refresh pace</span><strong>{{ chatStats.pollLabel }}</strong></li>
          </ul>
        </div>

        <section v-if="!isChatPage" class="nf-panel nf-filter-panel">
          <h2>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 3v7M6 14v7M18 3v4M18 11v10M3 10h6M15 7h6M3 17h6M15 14h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            </svg>
            Filter Posts
            <svg class="nf-panel-control" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
          </h2>
          <p class="nf-panel-copy">Browse by tag:</p>
          <div class="nf-tags">
            <button
              v-for="tag in TAGS"
              :key="tag"
              type="button"
              class="nf-tag"
              :class="{ active: search === tag }"
              @click="applyTag(tag)"
            >
              #{{ tag }}
            </button>
          </div>
          <div class="nf-filter-footer">
            <button type="button" @click="search = ''">Clear filters</button>
            <button type="button" @click="search = ''">Show all <span aria-hidden="true">&rarr;</span></button>
          </div>
        </section>

        <section v-if="!isChatPage" class="nf-panel nf-stats-panel">
          <h2>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 20V10M12 20V4M19 20v-7" stroke="currentColor" stroke-width="2" /></svg>
            Wall Stats
          </h2>
          <ul class="nf-stat-list">
            <li><span>Total posts</span><strong>{{ totalPosts }}</strong></li>
            <li><span>With media</span><strong>{{ withPhotos }}</strong></li>
            <li><span>Text only</span><strong>{{ textOnly }}</strong></li>
          </ul>
        </section>

        <section v-if="!isChatPage" class="nf-panel nf-rules-panel">
          <h2>
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.6-2.9 8.2-7 10-4.1-1.8-7-5.4-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.7" /></svg>
            Community Rules
          </h2>
          <ol class="nf-rule-list">
            <li v-for="(rule, index) in RULES" :key="rule[0]">
              <span class="nf-rule-number">{{ index + 1 }}</span>
              <span><strong>{{ rule[0] }}</strong><small>{{ rule[1] }}</small></span>
            </li>
          </ol>
          <p class="nf-rule-note">A safer, more honest BSU starts with all of us. <span aria-hidden="true">♥</span></p>
        </section>
      </aside>

      <main class="nf-main" :class="{ 'focus-main': isFocusPage }">
        <slot />
      </main>

      <aside v-if="!isAdminPage && !isFocusPage" class="nf-sidebar nf-right">
        <section class="nf-panel nf-trending-panel">
          <h2>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="m4 16 5-6 4 3 7-8M16 5h4v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            {{ isChatPage ? 'Recent Nicknames' : 'Trending Today' }}
          </h2>
          <ul v-if="isChatPage && recentChatNicknames.length" class="nf-highlights">
            <li v-for="nickname in recentChatNicknames" :key="nickname">
              <span class="nf-highlight-chip">{{ nickname.slice(0, 2).toUpperCase() }}</span>
              <p>{{ nickname }}</p>
            </li>
          </ul>
          <ol v-else-if="!isChatPage && highlights.length" class="nf-trending-list">
            <li v-for="(post, index) in highlights" :key="post.id">
              <span class="nf-trend-number">0{{ index + 1 }}</span>
              <span class="nf-trend-content">
                <strong>{{ excerpt(post.content, 58) }}</strong>
                <small>{{ Math.max(0, 24 - index * 6) }} likes <i>&bull;</i> #{{ postCategory(post) }}</small>
              </span>
            </li>
          </ol>
          <p v-else class="nf-empty">{{ isChatPage ? 'The room is quiet right now.' : 'Nothing posted yet.' }}</p>
          <a v-if="!isChatPage" href="#latest" class="nf-view-all">View all posts <span aria-hidden="true">&rarr;</span></a>
        </section>

        <section class="nf-panel nf-cta">
          <h2>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M4 5h16v11H9l-5 4v-4H4V5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
            {{ isChatPage ? 'Prefer something more permanent?' : 'Got Something to Say?' }}
          </h2>
          <p>
            {{ isChatPage ? 'Use the moderated wall if you want a post reviewed and featured publicly.' : 'Share your thoughts, confessions, or rants — anonymously.' }}
          </p>
          <p v-if="!isChatPage" class="nf-cta-secondary">Your post will be reviewed before it goes live.</p>
          <button v-if="isWallPage" type="button" class="nf-cta-btn" @click="requestComposer">Start a Discussion <span aria-hidden="true">&rarr;</span></button>
          <Link v-else href="/wall#composer" class="nf-cta-btn">{{ isChatPage ? 'Open submission form' : 'Start a Discussion' }} <span aria-hidden="true">&rarr;</span></Link>
          <small v-if="!isChatPage" class="nf-safe-note">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="6" y="10" width="12" height="10" stroke="currentColor" stroke-width="1.6"/><path d="M9 10V7a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.6"/></svg>
            Anonymous. Safe. Student-powered.
          </small>
        </section>

        <section class="nf-panel nf-tools-panel">
          <h2>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="5" height="5" stroke="currentColor" stroke-width="1.6"/><rect x="15" y="4" width="5" height="5" stroke="currentColor" stroke-width="1.6"/><rect x="4" y="15" width="5" height="5" stroke="currentColor" stroke-width="1.6"/><rect x="15" y="15" width="5" height="5" stroke="currentColor" stroke-width="1.6"/></svg>
            Other Tools
          </h2>
          <ul class="nf-app-list">
            <li>
              <a href="https://salidumay.com/" target="_blank" rel="noopener noreferrer" class="nf-app-link">
                <span class="nf-app-name">Free Movie Site</span>
                <span class="nf-app-url">salidumay.com</span>
              </a>
            </li>
            <li>
              <a href="https://bg-remover.salidumay.com/" target="_blank" rel="noopener noreferrer" class="nf-app-link">
                <span class="nf-app-name">AI Background Remover</span>
                <span class="nf-app-url">bg-remover.salidumay.com</span>
              </a>
            </li>
            <li>
              <a href="https://gpx2video.salidumay.com/" target="_blank" rel="noopener noreferrer" class="nf-app-link">
                <span class="nf-app-name">GPX to Video</span>
                <span class="nf-app-url">gpx2video.salidumay.com</span>
              </a>
            </li>
          </ul>
        </section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* Base palette, remapped onto the app-wide ramp in app.css. */
.nf-shell.dark,
.nf-shell.light {
  --nf-bg: var(--b-bg);
  --nf-panel: var(--b-bg);
  --nf-line: var(--b-200);
  --nf-ink: var(--b-ink);
  --nf-muted: var(--b-500);
  --nf-accent: var(--b-green);
  --nf-accent-contrast: var(--b-green-ink);
  --nf-surface-2: var(--b-50);
  --nf-hero-grad: linear-gradient(180deg, var(--b-50) 0%, var(--b-bg) 100%);
}

.nf-shell {
  width: 100%;
  min-height: 100vh;
  background: var(--nf-bg);
  color: var(--nf-ink);
  transition: background 0.2s ease, color 0.2s ease;
}

.nf-topbar {
  display: flex;
  align-items: center;
  gap: 2rem;
  padding: 0.85rem 1.5rem;
  border-bottom: 1px solid var(--nf-line);
}

.nf-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  font-weight: 700;
  white-space: nowrap;
}

.nf-brand-mark {
  width: 2rem;
  height: 2rem;
  border-radius: var(--b-r-sm);
  object-fit: cover;
  flex-shrink: 0;
}

.nf-tabs {
  display: flex;
  gap: 1.5rem;
  flex: 1;
}

.nf-tabs :deep(a) {
  color: var(--nf-muted);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.92rem;
  padding-bottom: 0.3rem;
  border-bottom: 2px solid transparent;
}

.nf-tabs :deep(a.active) {
  color: var(--nf-ink);
  border-bottom-color: var(--nf-accent);
}

.nf-admin-label {
  flex: 1;
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--nf-ink);
}

.nf-user {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.nf-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--b-r-sm);
  border: 1px solid var(--nf-line);
  background: var(--nf-panel);
  color: var(--nf-muted);
  cursor: pointer;
}

.nf-login-btn {
  background: var(--nf-accent);
  border: 1px solid var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.45rem 0.9rem;
  border-radius: var(--b-r-sm);
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.nf-login-btn:hover {
  background: var(--b-green-hover);
  border-color: var(--b-green-hover);
}

.nf-logout-btn {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  color: var(--nf-ink);
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.45rem 0.9rem;
  border-radius: var(--b-r-sm);
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.nf-logout-btn:hover {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.nf-body {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr) 280px;
  gap: 1.25rem;
  max-width: 1440px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.nf-body.admin-mode {
  grid-template-columns: minmax(0, 1fr);
  max-width: 1160px;
}

.nf-body.focus-mode {
  grid-template-columns: minmax(0, 1fr);
  max-width: 1360px;
}

.nf-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.nf-main.focus-main {
  width: 100%;
  max-width: 1280px;
  margin: 0 auto;
}

.nf-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-thumb);
  padding: 0.55rem 0.75rem;
  color: var(--nf-muted);
}

.nf-search input {
  flex: 1;
  background: none;
  border: none;
  outline: none;
  color: var(--nf-ink);
  font-size: 0.85rem;
}

.nf-panel {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-md);
  padding: 1rem;
}

.nf-panel h2 {
  margin: 0 0 0.75rem;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--nf-muted);
}

.nf-stat-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  font-size: 0.85rem;
}

.nf-stat-list li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.nf-stat-list strong {
  text-align: right;
}

.nf-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.nf-tag {
  background: var(--nf-surface-2);
  border: 1px solid var(--nf-line);
  color: var(--nf-muted);
  border-radius: var(--b-r-pill);
  padding: 0.3rem 0.65rem;
  font-size: 0.78rem;
  cursor: pointer;
}

.nf-tag.active {
  background: var(--nf-accent);
  border-color: var(--nf-accent);
  color: var(--nf-accent-contrast);
}

.nf-guidelines {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.82rem;
  color: var(--nf-muted);
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.nf-highlights {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.nf-highlights li {
  display: flex;
  gap: 0.6rem;
  align-items: flex-start;
}

.nf-highlights img,
.nf-highlight-chip {
  width: 40px;
  height: 40px;
  border-radius: var(--b-r-sm);
  flex-shrink: 0;
}

.nf-highlights img {
  object-fit: cover;
}

.nf-highlight-fallback {
  object-fit: contain;
  padding: 6px;
}

.nf-highlight-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--nf-surface-2);
  color: var(--nf-accent);
  font-size: 0.72rem;
  font-weight: 700;
}

.nf-highlights p {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.4;
  color: var(--nf-ink);
}

.nf-empty {
  font-size: 0.82rem;
  color: var(--nf-muted);
  margin: 0;
}

.nf-cta p {
  font-size: 0.82rem;
  color: var(--nf-muted);
  margin: 0 0 0.85rem;
  line-height: 1.4;
}

.nf-cta-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 0.55rem;
  border-radius: var(--b-r-sm);
  background: var(--nf-accent);
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.85rem;
}

.nf-app-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.nf-app-link {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 0.55rem 0.65rem;
  border-radius: var(--b-r-sm);
  text-decoration: none;
  transition: background 0.15s ease;
}

.nf-app-link:hover {
  background: var(--nf-surface-2);
}

.nf-app-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--nf-ink);
}

.nf-app-url {
  font-size: 0.75rem;
  color: var(--nf-accent);
}

@media (max-width: 1100px) {
  .nf-body {
    grid-template-columns: 1fr;
  }

  .nf-left {
    order: 2;
  }

  .nf-main {
    order: 1;
  }

  .nf-right {
    order: 3;
  }
}

@media (max-width: 640px) {
  .nf-topbar {
    flex-wrap: wrap;
    row-gap: 0.6rem;
    padding: 0.7rem 1rem;
  }

  .nf-brand {
    font-size: 0.95rem;
    gap: 0.45rem;
  }

  .nf-brand-mark {
    width: 1.7rem;
    height: 1.7rem;
  }

  .nf-tabs,
  .nf-admin-label {
    order: 3;
    flex-basis: 100%;
  }

  .nf-tabs {
    gap: 1.1rem;
    overflow-x: auto;
  }

  .nf-user {
    gap: 0.5rem;
    margin-left: auto;
  }

  .nf-login-btn,
  .nf-logout-btn {
    white-space: nowrap;
    font-size: 0.8rem;
    padding: 0.4rem 0.7rem;
  }

  .nf-body {
    padding: 1rem 1rem 2.5rem;
    gap: 1rem;
  }
}

/* ==========================================================================
   bryl-minimal — scoped to the landing page (/wall).
   Monochrome ramp, typography-driven hierarchy, hairline borders, no accent
   colour. The --nf-* tokens are re-mapped onto the ramp so every existing
   component rule above inherits the palette without being rewritten.
   ========================================================================== */

.nf-shell.bryl {
  font-family: var(--b-sans);
  font-size: 15px;
  transition: background 0.5s ease, color 0.5s ease, border-color 0.5s ease;
}

/* Re-map the legacy palette onto the ramp. Emphasis = inversion, so the
   "accent" is simply ink on background. */
.nf-shell.bryl {
  --nf-bg: var(--b-bg);
  --nf-panel: var(--b-bg);
  --nf-line: var(--b-200);
  --nf-ink: var(--b-ink);
  --nf-muted: var(--b-500);
  --nf-accent: var(--b-green);
  --nf-accent-contrast: var(--b-green-ink);
  --nf-surface-2: var(--b-50);
  --nf-hero-grad: linear-gradient(180deg, var(--b-50) 0%, var(--b-bg) 100%);
}

/* --- top bar ------------------------------------------------------------ */

.nf-shell.bryl .nf-topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  background: color-mix(in srgb, var(--b-bg) 90%, transparent);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--b-200);
}

.nf-shell.bryl .nf-brand {
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-ink);
}

/* Brand marks are desaturated so the chrome stays monochrome; user-submitted
   photos keep their own colour. */
.nf-shell.bryl .nf-brand-mark,
.nf-shell.bryl .nf-highlight-fallback {
  filter: grayscale(1);
}

.nf-shell.bryl .nf-brand-mark {
  width: 1.6rem;
  height: 1.6rem;
  border-radius: var(--b-r-thumb);
}

.nf-shell.bryl .nf-tabs :deep(a) {
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
  border-bottom: none;
  padding-bottom: 0;
  transition: color 0.2s ease;
}

.nf-shell.bryl .nf-tabs :deep(a:hover) {
  color: var(--b-ink);
}

.nf-shell.bryl .nf-tabs :deep(a.active) {
  color: var(--b-ink);
}

.nf-shell.bryl .nf-tabs :deep(a.active)::before {
  content: '→ ';
}

.nf-shell.bryl .nf-icon-btn {
  border-radius: var(--b-r-input);
  border-color: var(--b-200);
  background: var(--b-bg);
  color: var(--b-500);
  transition: color 0.2s ease, border-color 0.2s ease;
}

.nf-shell.bryl .nf-icon-btn:hover {
  color: var(--b-ink);
  border-color: var(--b-300);
}

.nf-shell.bryl .nf-login-btn,
.nf-shell.bryl .nf-logout-btn {
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  border-radius: var(--b-r-input);
  padding: 0.5rem 0.8rem;
}

.nf-shell.bryl .nf-login-btn {
  background: var(--b-green);
  border-color: var(--b-green);
  color: var(--b-green-ink);
}

.nf-shell.bryl .nf-login-btn:hover {
  background: var(--b-green-hover);
  border-color: var(--b-green-hover);
}

/* --- sidebars ----------------------------------------------------------- */

/* Page headings inside the slot are green too. Colour only — each page keeps
   its own type treatment. */
.nf-shell.bryl .nf-main :deep(h1),
.nf-shell.bryl .nf-main :deep(h2),
.nf-shell.bryl .nf-main :deep(h3) {
  color: var(--b-green-text);
}

.nf-shell.bryl .nf-panel {
  background: var(--b-bg);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-md);
  padding: 1.25rem;
  box-shadow: var(--b-shadow);
}

.nf-shell.bryl .nf-panel h2 {
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-green-text);
  margin-bottom: 0.9rem;
}

.nf-shell.bryl .nf-search {
  background: var(--b-50);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-input);
  color: var(--b-400);
}

.nf-shell.bryl .nf-search input {
  font-family: var(--b-mono);
  font-size: 13px;
  color: var(--b-ink);
}

.nf-shell.bryl .nf-search input::placeholder {
  color: var(--b-400);
}

.nf-shell.bryl .nf-stat-list {
  font-size: 13px;
  color: var(--b-500);
}

.nf-shell.bryl .nf-stat-list strong {
  font-family: var(--b-mono);
  font-weight: 500;
  color: var(--b-ink);
}

.nf-shell.bryl .nf-tag {
  background: transparent;
  border: 1px solid var(--b-300);
  border-radius: var(--b-r-pill);
  padding: 2px 8px;
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
  transition: color 0.2s ease, border-color 0.2s ease;
}

.nf-shell.bryl .nf-tag:hover {
  color: var(--b-green-text);
  border-color: var(--b-green);
}

/* The one loud element: an inverted chip. */
.nf-shell.bryl .nf-tag.active {
  background: var(--b-green);
  border-color: var(--b-green);
  color: var(--b-green-ink);
}

.nf-shell.bryl .nf-guidelines {
  font-size: 13px;
  color: var(--b-500);
  line-height: 1.6;
}

.nf-shell.bryl .nf-highlights img,
.nf-shell.bryl .nf-highlight-chip {
  border-radius: var(--b-r-thumb);
}

.nf-shell.bryl .nf-highlight-chip {
  background: var(--b-50);
  border: 1px solid var(--b-200);
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 1px;
  color: var(--b-500);
}

.nf-shell.bryl .nf-highlights p,
.nf-shell.bryl .nf-cta p,
.nf-shell.bryl .nf-empty {
  font-size: 13px;
  color: var(--b-500);
  line-height: 1.6;
}

.nf-shell.bryl .nf-highlights p {
  color: var(--b-ink);
}

.nf-shell.bryl .nf-cta-btn {
  background: var(--b-green);
  color: var(--b-green-ink);
  border-radius: var(--b-r-input);
  padding: 0.65rem;
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  transition: background 0.2s ease;
}

.nf-shell.bryl .nf-cta-btn:hover {
  background: var(--b-green-hover);
}

.nf-shell.bryl .nf-app-link {
  border-radius: var(--b-r-sm);
  border: 1px solid transparent;
  transition: border-color 0.2s ease;
}

.nf-shell.bryl .nf-app-link:hover {
  background: var(--b-50);
  border-color: var(--b-200);
}

.nf-shell.bryl .nf-app-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--b-ink);
}

.nf-shell.bryl .nf-app-name::after {
  content: ' ↗';
  color: var(--b-400);
}

.nf-shell.bryl .nf-app-url {
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-400);
}

@media (prefers-reduced-motion: reduce) {
  .nf-shell.bryl,
  .nf-shell.bryl :deep(*) {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
  }
}

/* Reference newsfeed composition ------------------------------------------------ */
.nf-shell.bryl {
  --nf-bg: #0a0e11;
  --nf-panel: rgba(9, 13, 16, 0.74);
  --nf-line: #2b3339;
  --nf-ink: #eef2f5;
  --nf-muted: #9ca7b4;
  --nf-accent: #1ed164;
  --nf-accent-contrast: #07150d;
  --nf-surface-2: rgba(22, 28, 33, 0.72);
  min-height: 100vh;
  background:
    radial-gradient(circle at 50% 20%, rgba(32, 44, 38, 0.08), transparent 32rem),
    linear-gradient(180deg, #090d10 0%, #0b1013 100%);
}

.nf-shell.bryl.light {
  --nf-bg: #f5f8f6;
  --nf-panel: #ffffff;
  --nf-line: #cbd5ce;
  --nf-ink: #0b1710;
  --nf-muted: #59665e;
  --nf-accent: #087a34;
  --nf-accent-contrast: #ffffff;
  --nf-surface-2: #eef3f0;
  background: #f5f8f6;
}

.nf-shell.bryl .nf-topbar {
  min-height: 61px;
  padding: 0 40px;
  gap: 34px;
  background: color-mix(in srgb, var(--nf-bg) 92%, transparent);
  border-color: var(--nf-line);
}

.nf-shell.bryl .nf-brand {
  min-width: 180px;
  gap: 16px;
  color: var(--nf-ink);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 1.2px;
}

.nf-shell.bryl .nf-brand-mark {
  width: 31px;
  height: 37px;
  padding: 3px;
  background: #24292d;
  border: 1px solid #30373d;
  filter: grayscale(1) brightness(1.45);
}

.nf-shell.bryl .nf-tabs {
  gap: 34px;
  align-items: center;
}

.nf-shell.bryl .nf-tabs :deep(a) {
  position: relative;
  padding-left: 0;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: .8px;
  color: var(--nf-muted);
}

.nf-shell.bryl .nf-tabs :deep(a.active) {
  padding-left: 18px;
  color: var(--nf-accent);
}

.nf-shell.bryl .nf-tabs :deep(a.active)::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  width: 6px;
  height: 6px;
  margin-top: -3px;
  border-radius: 50%;
  background: var(--nf-accent);
  box-shadow: 0 0 10px rgba(30, 209, 100, .4);
}

.nf-shell.bryl .nf-user {
  margin-left: auto;
  gap: 15px;
}

.nf-shell.bryl .nf-icon-btn {
  width: 38px;
  height: 38px;
  color: var(--nf-muted);
  border-color: var(--nf-line);
  background: rgba(11, 16, 19, .75);
}

.nf-shell.bryl .nf-login-btn,
.nf-shell.bryl .nf-logout-btn {
  min-width: 94px;
  padding: 10px 19px;
  text-align: center;
  background: linear-gradient(135deg, #0ab957, #049a48);
  border-color: #12c760;
  color: #f5fff8;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
}

.nf-shell.bryl .nf-body {
  grid-template-columns: minmax(255px, 355px) minmax(0, 796px) minmax(280px, 355px);
  gap: clamp(20px, 2.5vw, 42px);
  width: calc(100% - 80px);
  max-width: 1598px;
  padding: 24px 0 48px;
}

.nf-shell.bryl .nf-sidebar {
  gap: 16px;
}

.nf-shell.bryl .nf-search {
  height: 48px;
  gap: 12px;
  padding: 0 14px 0 16px;
  background: rgba(13, 18, 22, .72);
  border-color: var(--nf-line);
  color: #aeb9c6;
}

.nf-shell.bryl .nf-search input {
  font-family: var(--b-sans);
  font-size: 14px;
  color: var(--nf-ink);
}

.nf-shell.bryl .nf-search kbd {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 1px solid #394249;
  color: var(--nf-muted);
  background: #141a1e;
  font: 12px var(--b-mono);
}

.nf-shell.bryl .nf-panel {
  padding: 17px 17px 16px;
  background: var(--nf-panel);
  border-color: var(--nf-line);
  box-shadow: none;
}

.nf-shell.bryl .nf-panel h2 {
  display: flex;
  align-items: center;
  gap: 11px;
  margin: 0 0 16px;
  color: var(--nf-accent);
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 1.1px;
}

.nf-shell.bryl .nf-panel h2 svg {
  flex: 0 0 auto;
}

.nf-panel-control {
  margin-left: auto;
  color: var(--nf-muted);
}

.nf-panel-copy {
  margin: 0 0 10px;
  color: var(--nf-muted);
  font-size: 14px;
}

.nf-shell.bryl .nf-tags {
  gap: 7px 8px;
}

.nf-shell.bryl .nf-tag {
  padding: 6px 13px;
  border-color: #465058;
  color: #b7c1cd;
  background: rgba(16, 22, 26, .7);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .5px;
}

.nf-shell.bryl .nf-tag:hover,
.nf-shell.bryl .nf-tag.active {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
  background: rgba(30, 209, 100, .08);
}

.nf-filter-footer {
  display: flex;
  justify-content: space-between;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--nf-line);
}

.nf-filter-footer button {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--nf-muted);
  font: 12px var(--b-sans);
  cursor: pointer;
}

.nf-filter-footer button:hover {
  color: var(--nf-accent);
}

.nf-shell.bryl .nf-stat-list {
  gap: 9px;
  color: #a9b4c0;
  font-size: 14px;
}

.nf-shell.bryl .nf-stat-list strong {
  color: var(--nf-ink);
  font-size: 14px;
}

.nf-rule-list,
.nf-trending-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.nf-rule-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.nf-rule-list li {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.nf-rule-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  border-radius: 50%;
  border: 1px solid rgba(30, 209, 100, .3);
  background: rgba(30, 209, 100, .12);
  color: var(--nf-accent);
  font: 600 13px var(--b-mono);
}

.nf-rule-list strong,
.nf-rule-list small {
  display: block;
}

.nf-rule-list strong {
  color: var(--nf-ink);
  font-size: 13px;
  font-weight: 600;
}

.nf-rule-list small {
  margin-top: 2px;
  color: var(--nf-muted);
  font-size: 12px;
  line-height: 1.45;
}

.nf-rule-note {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin: 17px 0 0;
  padding: 11px 13px;
  border: 1px solid var(--nf-line);
  background: rgba(17, 23, 27, .75);
  color: var(--nf-muted);
  font-size: 12px;
  line-height: 1.35;
}

.nf-rule-note span {
  color: var(--nf-accent);
  font-size: 17px;
}

.nf-trending-list li {
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  gap: 14px;
  padding: 10px 0;
  border-bottom: 1px solid var(--nf-line);
}

.nf-trend-number {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 54px;
  background: linear-gradient(135deg, rgba(30, 209, 100, .07), rgba(30, 209, 100, .01));
  color: var(--nf-accent);
  font: 400 21px/1 var(--b-display);
}

.nf-trend-content {
  min-width: 0;
  padding-top: 2px;
}

.nf-trend-content strong {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  color: var(--nf-ink);
  font-size: 14px;
  font-weight: 500;
  line-height: 1.45;
}

.nf-trend-content small {
  display: block;
  margin-top: 5px;
  color: var(--nf-muted);
  font-size: 12px;
  text-transform: none;
}

.nf-trend-content i {
  padding: 0 6px;
  font-style: normal;
}

.nf-view-all {
  display: inline-flex;
  gap: 8px;
  margin-top: 14px;
  color: var(--nf-muted);
  font-size: 12px;
  text-decoration: none;
}

.nf-view-all:hover {
  color: var(--nf-accent);
}

.nf-shell.bryl .nf-cta p {
  margin-bottom: 8px;
  color: #b3bdc9;
  font-size: 14px;
  line-height: 1.45;
}

.nf-shell.bryl .nf-cta .nf-cta-secondary {
  color: var(--nf-muted);
}

.nf-shell.bryl .nf-cta-btn {
  gap: 11px;
  margin-top: 10px;
  border: 1px solid #15bd59;
  background: linear-gradient(135deg, #08b955, #039849);
  color: #f4fff7;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  cursor: pointer;
}

.nf-safe-note {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-top: 12px;
  color: var(--nf-muted);
  font-size: 12px;
}

.nf-shell.bryl .nf-app-list {
  gap: 2px;
}

.nf-shell.bryl .nf-app-link {
  position: relative;
  padding: 7px 8px 7px 43px;
}

.nf-shell.bryl .nf-app-link::before {
  content: '↗';
  position: absolute;
  left: 7px;
  top: 8px;
  color: var(--nf-muted);
  font-size: 16px;
}

.nf-shell.bryl .nf-app-name {
  font-size: 13px;
}

.nf-shell.bryl .nf-app-url {
  margin-top: 3px;
  color: #7c8996;
}

/* Light mode needs its own opaque surface treatment. The reference skin uses
   translucent near-black fills in dark mode; carrying those fills across the
   theme boundary produced the gray blocks and unreadable copy. */
.nf-shell.bryl.light .nf-icon-btn,
.nf-shell.bryl.light .nf-search,
.nf-shell.bryl.light .nf-tag {
  background: #ffffff;
  border-color: var(--nf-line);
  color: var(--nf-muted);
}

.nf-shell.bryl.light .nf-search input {
  color: var(--nf-ink);
}

.nf-shell.bryl.light .nf-search input::placeholder {
  color: #718078;
}

.nf-shell.bryl.light .nf-search kbd {
  border-color: var(--nf-line);
  background: var(--nf-surface-2);
  color: var(--nf-muted);
}

.nf-shell.bryl.light .nf-tag:hover,
.nf-shell.bryl.light .nf-tag.active {
  border-color: var(--nf-accent);
  background: #e9f7ee;
  color: var(--nf-accent);
}

.nf-shell.bryl.light .nf-stat-list,
.nf-shell.bryl.light .nf-cta p {
  color: var(--nf-muted);
}

.nf-shell.bryl.light .nf-rule-number {
  border-color: #9eddb5;
  background: #ebf9f0;
  color: var(--nf-accent);
}

.nf-shell.bryl.light .nf-rule-note {
  border-color: var(--nf-line);
  background: var(--nf-surface-2);
  color: var(--nf-muted);
}

.nf-shell.bryl.light .nf-trend-number {
  background: #eff9f2;
  color: var(--nf-accent);
}

.nf-shell.bryl.light .nf-app-url {
  color: #68766e;
}

@media (max-width: 1180px) {
  .nf-shell.bryl .nf-body {
    width: calc(100% - 40px);
    grid-template-columns: minmax(230px, 280px) minmax(0, 1fr);
    gap: 24px;
  }

  .nf-shell.bryl .nf-right {
    display: none;
  }
}

@media (max-width: 760px) {
  .nf-shell.bryl .nf-topbar {
    min-height: auto;
    padding: 11px 16px;
  }

  .nf-shell.bryl .nf-brand {
    min-width: 0;
  }

  .nf-shell.bryl .nf-body {
    width: 100%;
    grid-template-columns: 1fr;
    padding: 18px 16px 40px;
  }

  .nf-shell.bryl .nf-left {
    display: none;
  }
}

/* The public wall uses three columns, but focused/admin surfaces own the full
   content rail. Keep these more-specific rules after the responsive wall grid
   so the dashboard can never collapse into the left-column track. */
.nf-shell.bryl .nf-body.admin-mode {
  grid-template-columns: minmax(0, 1fr);
  width: calc(100% - 80px);
  max-width: 1160px;
}

.nf-shell.bryl .nf-body.focus-mode {
  grid-template-columns: minmax(0, 1fr);
  width: calc(100% - 80px);
  max-width: 1360px;
}

.nf-shell.bryl .nf-body.admin-mode .nf-main,
.nf-shell.bryl .nf-body.focus-mode .nf-main {
  width: 100%;
  min-width: 0;
}

@media (max-width: 760px) {
  .nf-shell.bryl .nf-body.admin-mode,
  .nf-shell.bryl .nf-body.focus-mode {
    width: 100%;
  }
}
</style>
