<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';

const page = usePage();
const isActive = (path) => computed(() => page.url === path || page.url.startsWith(`${path}?`));

const search = ref('');
provide('wallSearch', search);

const posts = computed(() => page.props.posts ?? []);

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
  <div class="nf-shell">
    <header class="nf-topbar">
      <span class="nf-brand">
        <span class="nf-brand-mark">FW</span>
        BSU Freedom Wall
      </span>

      <nav class="nf-tabs">
        <Link href="/" :class="{ active: isActive('/').value }">Discussions</Link>
        <Link href="/wall" :class="{ active: isActive('/wall').value }">News Feed</Link>
        <span class="nf-tab-disabled">Chat <em>soon</em></span>
      </nav>

      <div class="nf-user">
        <button type="button" class="nf-icon-btn" aria-label="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M12 3a6 6 0 0 0-6 6v3.5L4.5 15h15L18 12.5V9a6 6 0 0 0-6-6Z"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linejoin="round"
            />
            <path d="M9.5 18a2.5 2.5 0 0 0 5 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </button>
        <button type="button" class="nf-icon-btn" aria-label="Settings">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
            <path
              d="M19.4 13.5a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V19.5a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H4.5a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.56-1.11 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H10.5a1.7 1.7 0 0 0 1.04-1.56V4.5a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.09c.24.7.82 1.23 1.56 1.42"
              stroke="currentColor"
              stroke-width="1.2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
        <span class="nf-avatar" title="Browsing anonymously">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M21 11.5a8.5 8.5 0 0 1-12.36 7.58L4 20l1.02-4.55A8.5 8.5 0 1 1 21 11.5Z"
              stroke="currentColor"
              stroke-width="1.6"
            />
          </svg>
        </span>
        <span class="nf-greeting">Hello, Anonymous</span>
      </div>
    </header>

    <div class="nf-body">
      <aside class="nf-sidebar nf-left">
        <label class="nf-search">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" />
            <path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input v-model="search" type="text" placeholder="Search the wall…" />
        </label>

        <div class="nf-panel">
          <h2>Wall Stats</h2>
          <ul class="nf-stat-list">
            <li><span>Approved posts</span><strong>{{ posts.length }}</strong></li>
            <li><span>With photos</span><strong>{{ withPhotos }}</strong></li>
            <li><span>Text only</span><strong>{{ textOnly }}</strong></li>
          </ul>
        </div>

        <div class="nf-panel">
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
          <h2>Posting Guidelines</h2>
          <ul class="nf-guidelines">
            <li>Stay respectful, no personal attacks</li>
            <li>Don't share identifying info</li>
            <li>Every post is reviewed before it's live</li>
          </ul>
        </div>
      </aside>

      <main class="nf-main">
        <slot />
      </main>

      <aside class="nf-sidebar nf-right">
        <div class="nf-panel">
          <h2>Recent Highlights</h2>
          <ul v-if="highlights.length" class="nf-highlights">
            <li v-for="post in highlights" :key="post.id">
              <img v-if="post.image_urls?.length" :src="post.image_urls[0]" alt="" />
              <span v-else class="nf-highlight-fallback">FW</span>
              <p>{{ excerpt(post.content) }}</p>
            </li>
          </ul>
          <p v-else class="nf-empty">Nothing posted yet.</p>
        </div>

        <div class="nf-panel nf-cta">
          <h2>Got something to say?</h2>
          <p>Submit your own post anonymously — it'll show up here once reviewed.</p>
          <Link href="/" class="nf-cta-btn">Start a Discussion</Link>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.nf-shell {
  --nf-bg: #17181d;
  --nf-panel: #1f2027;
  --nf-line: #2c2d36;
  --nf-ink: #e9e9ee;
  --nf-muted: #9497a6;
  --nf-accent: #f2540b;

  width: 100vw;
  position: relative;
  left: 50%;
  transform: translateX(-50%);
  min-height: 100vh;
  background: var(--nf-bg);
  color: var(--nf-ink);
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 9px;
  background: var(--nf-accent);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
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

.nf-tab-disabled {
  color: #5a5c68;
  font-weight: 600;
  font-size: 0.92rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.nf-tab-disabled em {
  font-style: normal;
  font-size: 0.65rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 999px;
  padding: 0.1rem 0.4rem;
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
  border-radius: 8px;
  border: 1px solid var(--nf-line);
  background: var(--nf-panel);
  color: var(--nf-muted);
  cursor: pointer;
}

.nf-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  color: var(--nf-accent);
}

.nf-greeting {
  font-size: 0.85rem;
  color: var(--nf-muted);
  white-space: nowrap;
}

.nf-body {
  display: grid;
  grid-template-columns: 250px minmax(0, 1fr) 280px;
  gap: 1.25rem;
  max-width: 1440px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem 3rem;
}

.nf-sidebar {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.nf-search {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 10px;
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
  border-radius: 12px;
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
}

.nf-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.nf-tag {
  background: #2a2b33;
  border: 1px solid var(--nf-line);
  color: var(--nf-muted);
  border-radius: 999px;
  padding: 0.3rem 0.65rem;
  font-size: 0.78rem;
  cursor: pointer;
}

.nf-tag.active {
  background: var(--nf-accent);
  border-color: var(--nf-accent);
  color: #fff;
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

.nf-highlights img {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
}

.nf-highlight-fallback {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: #2a2b33;
  color: var(--nf-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  flex-shrink: 0;
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
  border-radius: 8px;
  background: var(--nf-accent);
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.85rem;
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
</style>
