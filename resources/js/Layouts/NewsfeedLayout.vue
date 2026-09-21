<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, provide, ref, watchEffect } from 'vue';

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
provide('wallSearch', search);

const theme = ref('dark');

onMounted(() => {
  const saved = localStorage.getItem('wall-theme');
  if (saved === 'light' || saved === 'dark') theme.value = saved;
});

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

const withPhotos = computed(() => posts.value.filter((p) => p.image_urls?.length).length);
const textOnly = computed(() => posts.value.length - withPhotos.value);
const highlights = computed(() => posts.value.slice(0, 3));

const TAGS = ['confession', 'crush', 'exam', 'campus', 'org', 'rant'];

function applyTag(tag) {
  search.value = search.value === tag ? '' : tag;
}

function excerpt(text, length = 60) {
  if (!text) return '';
  return text.length > length ? `${text.slice(0, length).trim()}…` : text;
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
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" />
            <path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input v-model="search" type="text" placeholder="Search the wall…" />
        </label>

        <div class="nf-panel">
          <h2>{{ isChatPage ? 'Chat Snapshot' : 'Wall Stats' }}</h2>
          <ul class="nf-stat-list">
            <template v-if="isChatPage">
              <li><span>Your nickname</span><strong>{{ chatNickname }}</strong></li>
              <li><span>Total messages</span><strong>{{ chatStats.totalMessages }}</strong></li>
              <li><span>Messages today</span><strong>{{ chatStats.messagesToday }}</strong></li>
              <li><span>Refresh pace</span><strong>{{ chatStats.pollLabel }}</strong></li>
            </template>
            <template v-else>
              <li><span>Approved posts</span><strong>{{ posts.length }}</strong></li>
              <li><span>With photos</span><strong>{{ withPhotos }}</strong></li>
              <li><span>Text only</span><strong>{{ textOnly }}</strong></li>
            </template>
          </ul>
        </div>

        <div v-if="!isChatPage" class="nf-panel">
          <h2>Browse Tags</h2>
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
        </div>

        <div class="nf-panel">
          <h2>{{ isChatPage ? 'Chat Guidelines' : 'Posting Guidelines' }}</h2>
          <ul class="nf-guidelines">
            <template v-if="isChatPage">
              <li>Keep it campus-safe and respectful</li>
              <li>No threats, doxxing, or targeted harassment</li>
              <li>Messages are filtered and rate-limited automatically</li>
            </template>
            <template v-else>
              <li>Stay respectful, no personal attacks</li>
              <li>Don't share identifying info</li>
              <li>Every post is reviewed before it's live</li>
            </template>
          </ul>
        </div>
      </aside>

      <main class="nf-main" :class="{ 'focus-main': isFocusPage }">
        <slot />
      </main>

      <aside v-if="!isAdminPage && !isFocusPage" class="nf-sidebar nf-right">
        <div class="nf-panel">
          <h2>{{ isChatPage ? 'Recent Nicknames' : 'Recent Highlights' }}</h2>
          <ul v-if="isChatPage && recentChatNicknames.length" class="nf-highlights">
            <li v-for="nickname in recentChatNicknames" :key="nickname">
              <span class="nf-highlight-chip">{{ nickname.slice(0, 2).toUpperCase() }}</span>
              <p>{{ nickname }}</p>
            </li>
          </ul>
          <ul v-else-if="!isChatPage && highlights.length" class="nf-highlights">
            <li v-for="post in highlights" :key="post.id">
              <img v-if="post.image_urls?.length" :src="post.image_urls[0]" alt="" />
              <img v-else src="/images/branding/bsufw-mark-64.png" alt="" class="nf-highlight-fallback" />
              <p>{{ excerpt(post.content) }}</p>
            </li>
          </ul>
          <p v-else class="nf-empty">{{ isChatPage ? 'The room is quiet right now.' : 'Nothing posted yet.' }}</p>
        </div>

        <div class="nf-panel nf-cta">
          <h2>{{ isChatPage ? 'Prefer something more permanent?' : 'Got something to say?' }}</h2>
          <p>
            {{ isChatPage ? 'Use the moderated wall if you want a post reviewed and featured publicly.' : "Submit your own post anonymously — it'll show up here once reviewed." }}
          </p>
          <a v-if="isWallPage" href="#composer" class="nf-cta-btn">Start a Discussion</a>
          <Link v-else href="/wall#composer" class="nf-cta-btn">{{ isChatPage ? 'Open submission form' : 'Start a Discussion' }}</Link>
        </div>

        <div class="nf-panel">
          <h2>Apps</h2>
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
        </div>
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
</style>
