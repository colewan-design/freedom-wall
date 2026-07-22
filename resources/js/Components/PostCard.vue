<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { timeAgo } from '../lib/date';

const props = defineProps({
  post: { type: Object, required: true },
  currentUserId: { type: Number, required: true },
  saved: { type: Boolean, default: false },
});

const isOwner = props.post.user.id === props.currentUserId;
const menuOpen = ref(false);
const cardEl = ref(null);

function toggleSave() {
  if (props.saved) {
    router.delete(route('posts.unsave', props.post.id), { preserveScroll: true });
  } else {
    router.post(route('posts.save', props.post.id), {}, { preserveScroll: true });
  }
}

function destroyPost() {
  menuOpen.value = false;
  if (!confirm('Delete this post?')) return;
  router.delete(route('posts.destroy', props.post.id), { preserveScroll: true });
}

function onDocumentClick(e) {
  if (menuOpen.value && cardEl.value && !cardEl.value.contains(e.target)) {
    menuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
  <article ref="cardEl" class="post-card">
    <div class="post-header">
      <Link :href="`/profile/${post.user.username}`" class="post-avatar">
        <img v-if="post.user.avatar_url" :src="post.user.avatar_url" alt="" />
        <span v-else>{{ post.user.name.slice(0, 1).toUpperCase() }}</span>
      </Link>
      <div class="post-author">
        <Link :href="`/profile/${post.user.username}`" class="post-name">{{ post.user.name }}</Link>
        <span class="post-meta">@{{ post.user.username }} · {{ timeAgo(post.created_at) }}</span>
      </div>
      <div v-if="isOwner" class="post-menu-wrap">
        <button type="button" class="post-menu-btn" title="Post options" @click="menuOpen = !menuOpen">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="5" cy="12" r="1.6" />
            <circle cx="12" cy="12" r="1.6" />
            <circle cx="19" cy="12" r="1.6" />
          </svg>
        </button>
        <div v-if="menuOpen" class="post-menu">
          <button type="button" class="post-menu-item" @click="destroyPost">Delete post</button>
        </div>
      </div>
    </div>

    <p class="post-content">{{ post.content }}</p>

    <div v-if="post.image_urls?.length" class="post-images" :class="`count-${Math.min(post.image_urls.length, 4)}`">
      <img v-for="(url, i) in post.image_urls" :key="i" :src="url" alt="" />
    </div>

    <div class="post-actions">
      <span class="action inert" title="Coming soon">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M12 20.5s-7.5-4.6-7.5-10A4.5 4.5 0 0 1 12 7.2a4.5 4.5 0 0 1 7.5 3.3c0 5.4-7.5 10-7.5 10Z"
            stroke="currentColor"
            stroke-width="1.4"
            stroke-linejoin="round"
          />
        </svg>
        Like
      </span>
      <span class="action inert" title="Coming soon">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M4 5.5h16v9H9l-4 3.5v-3.5H4v-9Z"
            stroke="currentColor"
            stroke-width="1.4"
            stroke-linejoin="round"
          />
        </svg>
        Comment
      </span>
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
  border-radius: 16px;
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

.post-menu-wrap {
  position: relative;
  flex-shrink: 0;
}

.post-menu-btn {
  background: none;
  border: none;
  color: var(--nf-muted);
  cursor: pointer;
  padding: 0.3rem;
  border-radius: 6px;
}

.post-menu-btn:hover {
  background: var(--nf-surface-2);
}

.post-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 0.25rem);
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 10px;
  box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.35);
  overflow: hidden;
  z-index: 10;
  min-width: 140px;
}

.post-menu-item {
  display: block;
  width: 100%;
  text-align: left;
  background: none;
  border: none;
  padding: 0.6rem 0.9rem;
  color: #dc2626;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

.post-menu-item:hover {
  background: var(--nf-surface-2);
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
  border-radius: 10px;
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

.action:not(.inert):hover {
  background: var(--nf-surface-2);
}

.action.inert {
  cursor: default;
  opacity: 0.55;
}

.action.active {
  color: var(--nf-accent);
}
</style>
