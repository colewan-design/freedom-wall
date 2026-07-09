<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, inject, reactive, ref } from 'vue';
import NewsfeedLayout from '../Layouts/NewsfeedLayout.vue';
import { timeAgo } from '../lib/date';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  posts: { type: Array, required: true },
});

const search = inject('wallSearch', ref(''));

const filteredPosts = computed(() => {
  const query = search.value.trim().toLowerCase();
  if (!query) return props.posts;
  return props.posts.filter((post) => post.content?.toLowerCase().includes(query));
});

const reactions = reactive({});

function reactionFor(post) {
  if (!reactions[post.id]) {
    reactions[post.id] = { liked: false, saved: false };
  }
  return reactions[post.id];
}

function toggleLike(post) {
  reactionFor(post).liked = !reactionFor(post).liked;
}

function toggleSave(post) {
  reactionFor(post).saved = !reactionFor(post).saved;
}

async function sharePost(post) {
  const url = `${window.location.origin}/wall#post-${post.id}`;
  if (navigator.share) {
    try {
      await navigator.share({ title: 'BSU Freedom Wall', text: post.content, url });
      return;
    } catch {
      // user cancelled or share failed, fall through to clipboard
    }
  }
  await navigator.clipboard?.writeText(url);
}
</script>

<template>
  <div class="wall-page">
    <div class="hero">
      <div class="hero-text">
        <img src="/images/branding/bsufw-banner.png" alt="BSU Freedom Wall" class="hero-banner" />
        <span class="hero-eyebrow">BSU Community</span>
        <h1>Speak freely. Stay anonymous.</h1>
        <p>Confessions, rants, and stories from the BSU community — reviewed and posted for everyone to read.</p>
      </div>
    </div>

    <div class="feed-header">
      <h2>Latest Newsfeed</h2>
      <Link href="/" class="start-btn">Start a Discussion</Link>
    </div>

    <p v-if="filteredPosts.length === 0" class="hint">
      {{ posts.length === 0 ? 'No approved posts yet.' : 'No posts match your search.' }}
    </p>

    <div v-else class="feed-list">
      <article v-for="post in filteredPosts" :id="`post-${post.id}`" :key="post.id" class="feed-card">
        <div class="feed-thumb" :class="{ 'no-image': !post.image_urls?.length }">
          <img v-if="post.image_urls?.length" :src="post.image_urls[0]" alt="" />
          <img v-else src="/images/branding/bsufw-mark-64.png" alt="" class="feed-thumb-fallback" />
        </div>

        <div class="feed-body">
          <div class="feed-meta">
            <span class="byline">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                  d="M21 11.5a8.5 8.5 0 0 1-12.36 7.58L4 20l1.02-4.55A8.5 8.5 0 1 1 21 11.5Z"
                  stroke="currentColor"
                  stroke-width="1.6"
                />
              </svg>
              Anonymous
            </span>
            <span class="dot">&middot;</span>
            <span class="timestamp">{{ timeAgo(post.reviewed_at) }}</span>
            <span v-if="post.fb_post_id" class="fb-badge" title="Also posted on Facebook">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                  d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"
                />
              </svg>
            </span>
          </div>

          <p class="feed-content">{{ post.content }}</p>

          <div v-if="post.image_urls?.length > 1" class="feed-gallery">
            <img v-for="(imageUrl, index) in post.image_urls.slice(1)" :key="index" :src="imageUrl" alt="" />
          </div>

          <div class="feed-actions">
            <button type="button" class="action" :class="{ active: reactionFor(post).liked }" @click="toggleLike(post)">
              <svg width="15" height="15" viewBox="0 0 24 24" :fill="reactionFor(post).liked ? 'currentColor' : 'none'" aria-hidden="true">
                <path
                  d="M7 10v11H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1h3Zm0 0 4.5-8a2 2 0 0 1 2.24.6c.5.6.7 1.4.48 2.16L13 10h5.2a2 2 0 0 1 1.96 2.4l-1.5 7A2 2 0 0 1 16.7 21H10a3 3 0 0 1-3-3"
                  stroke="currentColor"
                  stroke-width="1.4"
                  stroke-linejoin="round"
                />
              </svg>
              Like
            </button>
            <button type="button" class="action" @click="sharePost(post)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="18" cy="5" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <circle cx="6" cy="12" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <circle cx="18" cy="19" r="2.4" stroke="currentColor" stroke-width="1.4" />
                <path d="m8.1 10.8 7.8-4.6M8.1 13.2l7.8 4.6" stroke="currentColor" stroke-width="1.4" />
              </svg>
              Share
            </button>
            <button type="button" class="action" :class="{ active: reactionFor(post).saved }" @click="toggleSave(post)">
              <svg width="15" height="15" viewBox="0 0 24 24" :fill="reactionFor(post).saved ? 'currentColor' : 'none'" aria-hidden="true">
                <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
              </svg>
              Save
            </button>
          </div>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.wall-page {
  max-width: 100%;
}

.hero {
  border-radius: 16px;
  padding: 2rem;
  margin-bottom: 1.5rem;
  background: var(--nf-hero-grad);
  border: 1px solid var(--nf-line);
}

.hero-banner {
  display: block;
  max-width: 320px;
  width: 100%;
  height: auto;
  margin-bottom: 1rem;
  border-radius: 8px;
}

.hero-eyebrow {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--nf-accent);
  margin-bottom: 0.5rem;
}

.hero-text h1 {
  margin: 0 0 0.5rem;
  font-size: 1.7rem;
  font-weight: 800;
  color: var(--nf-ink);
}

.hero-text p {
  margin: 0;
  color: var(--nf-muted);
  max-width: 46ch;
  font-size: 0.92rem;
  line-height: 1.5;
}

.feed-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.feed-header h2 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--nf-ink);
}

.start-btn {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 0.5rem 0.9rem;
  border-radius: 8px;
}

.hint {
  color: var(--nf-muted);
}

.feed-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.feed-card {
  display: flex;
  gap: 1rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 1rem;
}

.feed-thumb {
  width: 84px;
  height: 84px;
  border-radius: 10px;
  overflow: hidden;
  flex-shrink: 0;
}

.feed-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.feed-thumb.no-image {
  background: var(--nf-surface-2);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px;
}

.feed-thumb-fallback {
  width: 100% !important;
  height: 100% !important;
  object-fit: contain !important;
}

.feed-body {
  flex: 1;
  min-width: 0;
}

.feed-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: var(--nf-muted);
  margin-bottom: 0.4rem;
}

.byline {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-weight: 600;
  color: var(--nf-ink);
}

.fb-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--nf-surface-2);
  color: var(--nf-accent);
}

.feed-content {
  margin: 0 0 0.6rem;
  white-space: pre-wrap;
  line-height: 1.5;
  color: var(--nf-ink);
  font-size: 0.92rem;
}

.feed-gallery {
  display: flex;
  gap: 0.4rem;
  margin-bottom: 0.6rem;
}

.feed-gallery img {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
}

.feed-actions {
  display: flex;
  gap: 1.25rem;
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: none;
  border: none;
  color: var(--nf-muted);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.2rem 0;
}

.action:hover {
  color: var(--nf-ink);
}

.action.active {
  color: var(--nf-accent);
}
</style>
