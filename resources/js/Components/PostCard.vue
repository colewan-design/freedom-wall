<script setup>
import { Link, router } from '@inertiajs/vue3';
import { timeAgo } from '../lib/date';

const props = defineProps({
  post: { type: Object, required: true },
  currentUserId: { type: Number, required: true },
  saved: { type: Boolean, default: false },
});

const isOwner = props.post.user.id === props.currentUserId;

function toggleSave() {
  if (props.saved) {
    router.delete(route('posts.unsave', props.post.id), { preserveScroll: true });
  } else {
    router.post(route('posts.save', props.post.id), {}, { preserveScroll: true });
  }
}

function destroyPost() {
  if (!confirm('Delete this post?')) return;
  router.delete(route('posts.destroy', props.post.id), { preserveScroll: true });
}
</script>

<template>
  <article class="post-card">
    <div class="post-header">
      <Link :href="`/profile/${post.user.username}`" class="post-avatar">
        <img v-if="post.user.avatar_url" :src="post.user.avatar_url" alt="" />
        <span v-else>{{ post.user.name.slice(0, 1).toUpperCase() }}</span>
      </Link>
      <div class="post-author">
        <Link :href="`/profile/${post.user.username}`" class="post-name">{{ post.user.name }}</Link>
        <span class="post-meta">@{{ post.user.username }} · {{ timeAgo(post.created_at) }}</span>
      </div>
      <button v-if="isOwner" type="button" class="post-delete" title="Delete post" @click="destroyPost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </button>
    </div>

    <p class="post-content">{{ post.content }}</p>

    <div v-if="post.image_urls?.length" class="post-images" :class="`count-${Math.min(post.image_urls.length, 4)}`">
      <img v-for="(url, i) in post.image_urls" :key="i" :src="url" alt="" />
    </div>

    <div class="post-actions">
      <button type="button" class="action" :class="{ active: saved }" @click="toggleSave">
        <svg width="15" height="15" viewBox="0 0 24 24" :fill="saved ? 'currentColor' : 'none'" aria-hidden="true">
          <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
        </svg>
        {{ saved ? 'Saved' : 'Save' }}
      </button>
    </div>
  </article>
</template>

<style scoped>
.post-card {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.post-header {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.post-avatar {
  width: 2.5rem;
  height: 2.5rem;
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

.post-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.post-author {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
}

.post-name {
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--nf-ink);
  text-decoration: none;
}

.post-meta {
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.post-delete {
  background: none;
  border: none;
  color: var(--nf-muted);
  cursor: pointer;
  padding: 0.3rem;
  flex-shrink: 0;
}

.post-delete:hover {
  color: var(--danger, #dc2626);
}

.post-content {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.5;
  color: var(--nf-ink);
}

.post-images {
  display: grid;
  gap: 0.4rem;
  grid-template-columns: 1fr;
}

.post-images.count-2,
.post-images.count-3,
.post-images.count-4 {
  grid-template-columns: 1fr 1fr;
}

.post-images img {
  width: 100%;
  height: 100%;
  max-height: 420px;
  object-fit: cover;
  border-radius: 8px;
}

.post-actions {
  display: flex;
  gap: 0.5rem;
  border-top: 1px solid var(--nf-line);
  padding-top: 0.6rem;
}

.action {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: none;
  border: none;
  color: var(--nf-muted);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.3rem 0.5rem;
  border-radius: 6px;
}

.action:hover {
  background: var(--nf-surface-2);
}

.action.active {
  color: var(--nf-accent);
}
</style>
