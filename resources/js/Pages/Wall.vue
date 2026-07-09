<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { timeAgo } from '../lib/date';

defineOptions({ layout: AppLayout });

const props = defineProps({
  posts: { type: Array, required: true },
});

const ACCENTS = ['accent-sage', 'accent-cream', 'accent-slate', 'accent-gold'];

function accentFor(post) {
  return ACCENTS[post.id % ACCENTS.length];
}
</script>

<template>
  <section class="wall-page">
    <div class="wall-inner">
      <h1>The Wall</h1>
      <p class="subtext">Anonymous posts from the BSU community, approved and live.</p>

      <p v-if="posts.length === 0" class="hint">No approved posts yet.</p>

      <div v-else class="grid">
        <article
          v-for="post in posts"
          :key="post.id"
          class="card"
          :class="{ [accentFor(post)]: !post.image_urls?.length }"
        >
          <div v-if="post.image_urls?.length" class="card-gallery" :class="`count-${Math.min(post.image_urls.length, 4)}`">
            <img
              v-for="(imageUrl, index) in post.image_urls"
              :key="`${post.id}-${index}`"
              :src="imageUrl"
              alt=""
              class="card-image"
            />
          </div>
          <p class="card-content">{{ post.content }}</p>
          <div class="card-footer">
            <span class="byline">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path
                  d="M21 11.5a8.5 8.5 0 0 1-12.36 7.58L4 20l1.02-4.55A8.5 8.5 0 1 1 21 11.5Z"
                  stroke="currentColor"
                  stroke-width="1.6"
                />
              </svg>
              Anonymous
            </span>
            <span class="timestamp">{{ timeAgo(post.reviewed_at) }}</span>
            <span v-if="post.fb_post_id" class="fb-badge" title="Also posted on Facebook">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path
                  d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"
                />
              </svg>
            </span>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<style scoped>
.wall-page {
  width: 100vw;
  position: relative;
  left: 50%;
  transform: translateX(-50%);
}

.wall-inner {
  max-width: 1180px;
  margin: 0 auto;
  padding: 0 1.5rem 3rem;
}

h1 {
  font-family: 'Fraunces', Georgia, serif;
  font-weight: 500;
  font-size: 2.2rem;
  color: var(--ink);
  margin-bottom: 0.25rem;
}

.subtext {
  color: var(--muted);
  margin-bottom: 1.75rem;
}

.hint {
  color: #555;
}

.grid {
  columns: 4 260px;
  column-gap: 1.1rem;
}

@media (max-width: 900px) {
  .grid {
    columns: 2 220px;
  }
}

@media (max-width: 520px) {
  .grid {
    columns: 1;
  }
}

.card {
  break-inside: avoid;
  margin-bottom: 1.1rem;
  border-radius: 18px;
  overflow: hidden;
  background: #fff;
  border: 1px solid var(--line);
  display: flex;
  flex-direction: column;
}

.card-image {
  width: 100%;
  display: block;
  object-fit: cover;
  aspect-ratio: 4 / 3;
}

.card-gallery {
  display: grid;
  gap: 0.25rem;
  padding: 0.25rem;
  background: #f6f3ee;
}

.card-gallery.count-1 {
  grid-template-columns: 1fr;
}

.card-gallery.count-2 {
  grid-template-columns: repeat(2, 1fr);
}

.card-gallery.count-3,
.card-gallery.count-4 {
  grid-template-columns: repeat(2, 1fr);
}

.card-content {
  margin: 0;
  padding: 1rem 1rem 0.75rem;
  white-space: pre-wrap;
  line-height: 1.5;
  color: var(--ink);
  font-size: 0.95rem;
}

.card.accent-sage {
  background: #e4ede4;
  border-color: #d3e0d3;
}

.card.accent-cream {
  background: #f6efdd;
  border-color: #ecdfc0;
}

.card.accent-slate {
  background: #e6eaee;
  border-color: #d6dce2;
}

.card.accent-gold {
  background: #fbe6d4;
  border-color: #f2d3ac;
}

.card.accent-sage .card-content,
.card.accent-cream .card-content,
.card.accent-slate .card-content,
.card.accent-gold .card-content {
  padding-top: 1.25rem;
  font-family: 'Fraunces', Georgia, serif;
  font-size: 1.1rem;
  font-weight: 500;
}

.card-footer {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.6rem 1rem 1rem;
  font-size: 0.75rem;
  color: var(--muted);
  margin-top: auto;
}

.byline {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-weight: 600;
}

.timestamp {
  margin-left: auto;
}

.fb-badge {
  display: inline-flex;
  color: #1877f2;
}
</style>
