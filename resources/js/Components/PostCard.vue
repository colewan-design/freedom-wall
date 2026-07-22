<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { timeAgo } from '../lib/date';

const props = defineProps({
  post: { type: Object, required: true },
  currentUserId: { type: Number, required: true },
  saved: { type: Boolean, default: false },
  viewerReaction: { type: String, default: null },
  tint: { type: String, default: 'blue' },
});

const REACTIONS = {
  fire: { emoji: '🔥', label: 'Fire' },
  love: { emoji: '😍', label: 'Love' },
  sad: { emoji: '😢', label: 'Sad' },
  heart: { emoji: '❤️', label: 'Heart' },
};

const isOwner = props.post.user.id === props.currentUserId;
const menuOpen = ref(false);
const cardEl = ref(null);

const pickerOpen = ref(false);
const toast = ref(null);
let toastTimer = null;
let hoverTimer = null;

function openPickerOnHover() {
  hoverTimer = window.setTimeout(() => {
    pickerOpen.value = true;
  }, 250);
}

function cancelHoverOpen() {
  if (hoverTimer) window.clearTimeout(hoverTimer);
}

function showToast(type) {
  toast.value = REACTIONS[type];
  if (toastTimer) window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => {
    toast.value = null;
  }, 1600);
}

function react(type) {
  pickerOpen.value = false;
  showToast(type);
  router.post(route('posts.react', props.post.id), { type }, { preserveScroll: true });
}

function toggleLike() {
  if (props.viewerReaction) {
    router.delete(route('posts.unreact', props.post.id), { preserveScroll: true });
  } else {
    react('heart');
  }
}

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

const commentsOpen = ref(false);
const commentForm = useForm({ content: '' });

function submitComment() {
  if (!commentForm.content.trim()) return;
  commentForm.post(route('posts.comments.store', props.post.id), {
    preserveScroll: true,
    onSuccess: () => commentForm.reset(),
  });
}

function onDocumentClick(e) {
  if (menuOpen.value && cardEl.value && !cardEl.value.contains(e.target)) {
    menuOpen.value = false;
  }
  if (pickerOpen.value && cardEl.value && !cardEl.value.contains(e.target)) {
    pickerOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick);
  if (toastTimer) window.clearTimeout(toastTimer);
  if (hoverTimer) window.clearTimeout(hoverTimer);
});
</script>

<template>
  <article ref="cardEl" class="post-card" :class="`tint-${tint}`">
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

    <div class="post-stats">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path
          d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linejoin="round"
        />
        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5" />
      </svg>
      {{ post.views_count ?? 0 }}
    </div>

    <div class="post-actions">
      <div class="reaction-wrap" @mouseenter="openPickerOnHover" @mouseleave="cancelHoverOpen">
        <button type="button" class="action" :class="{ active: viewerReaction }" @click="toggleLike">
          <span v-if="viewerReaction">{{ REACTIONS[viewerReaction].emoji }}</span>
          <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M12 20.5s-7.5-4.6-7.5-10A4.5 4.5 0 0 1 12 7.2a4.5 4.5 0 0 1 7.5 3.3c0 5.4-7.5 10-7.5 10Z"
              stroke="currentColor"
              stroke-width="1.4"
              stroke-linejoin="round"
            />
          </svg>
          {{ viewerReaction ? REACTIONS[viewerReaction].label : 'Like' }}
          <span class="reaction-count" v-if="post.reactions_count">{{ post.reactions_count }}</span>
        </button>

        <div v-if="pickerOpen" class="reaction-picker">
          <button
            v-for="(meta, type) in REACTIONS"
            :key="type"
            type="button"
            class="reaction-option"
            :title="meta.label"
            @click="react(type)"
          >
            {{ meta.emoji }}
          </button>
          <button type="button" class="reaction-close" aria-label="Close" @click="pickerOpen = false">✕</button>
        </div>

        <div v-if="toast" class="reaction-toast">{{ toast.emoji }} {{ toast.label }}!</div>
      </div>

      <button type="button" class="action" :class="{ active: commentsOpen }" @click="commentsOpen = !commentsOpen">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M4 5.5h16v9H9l-4 3.5v-3.5H4v-9Z"
            stroke="currentColor"
            stroke-width="1.4"
            stroke-linejoin="round"
          />
        </svg>
        Comment
        <span class="reaction-count" v-if="post.comments_count">{{ post.comments_count }}</span>
      </button>

      <button type="button" class="action" :class="{ active: saved }" @click="toggleSave">
        <svg width="15" height="15" viewBox="0 0 24 24" :fill="saved ? 'currentColor' : 'none'" aria-hidden="true">
          <path d="M6 4h12v16l-6-4-6 4V4Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
        </svg>
        {{ saved ? 'Saved' : 'Save' }}
      </button>
    </div>

    <div v-if="commentsOpen" class="comments-section">
      <div v-if="post.comments?.length" class="comment-list">
        <div v-for="comment in post.comments" :key="comment.id" class="comment-row">
          <span class="comment-avatar">
            <img v-if="comment.user?.avatar_url" :src="comment.user.avatar_url" alt="" />
            <span v-else>{{ comment.user?.name.slice(0, 1).toUpperCase() }}</span>
          </span>
          <div class="comment-bubble">
            <span class="comment-name">{{ comment.user?.name }}</span>
            <p class="comment-content">{{ comment.content }}</p>
          </div>
          <span class="comment-time">{{ timeAgo(comment.created_at) }}</span>
        </div>
      </div>
      <p v-else class="comments-empty">No comments yet — be the first to say something.</p>

      <form class="comment-form" @submit.prevent="submitComment">
        <input v-model="commentForm.content" type="text" placeholder="Write a comment…" maxlength="500" />
        <button type="submit" :disabled="commentForm.processing || !commentForm.content.trim()">Send</button>
      </form>
      <p v-if="commentForm.errors.content" class="comment-error">{{ commentForm.errors.content }}</p>
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

.post-card.tint-blue {
  background: var(--post-tint-blue, var(--nf-panel));
}

.post-card.tint-cream {
  background: var(--post-tint-cream, var(--nf-panel));
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

.post-images.count-2 {
  grid-template-columns: 1fr 1fr;
}

.post-images.count-3 {
  grid-template-columns: 1fr 1fr 1fr;
}

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

.post-images.count-3 img {
  max-height: 220px;
}

.post-stats {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.post-actions {
  display: flex;
  gap: 0.5rem;
  border-top: 1px solid var(--nf-line);
  padding-top: 0.6rem;
}

.reaction-wrap {
  position: relative;
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

.reaction-count {
  color: var(--nf-muted);
  font-weight: 500;
}

.reaction-picker {
  position: absolute;
  bottom: calc(100% + 0.4rem);
  left: 0;
  display: flex;
  align-items: center;
  gap: 0.15rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 999px;
  padding: 0.35rem 0.5rem;
  box-shadow: 0 8px 24px -8px rgba(0, 0, 0, 0.35);
  z-index: 10;
}

.reaction-option {
  background: none;
  border: none;
  font-size: 1.3rem;
  line-height: 1;
  cursor: pointer;
  padding: 0.2rem;
  border-radius: 50%;
  transition: transform 0.1s ease;
}

.reaction-option:hover {
  transform: scale(1.25);
}

.reaction-close {
  background: var(--nf-surface-2);
  border: none;
  color: var(--nf-muted);
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 50%;
  font-size: 0.7rem;
  cursor: pointer;
  margin-left: 0.2rem;
}

.reaction-toast {
  position: absolute;
  bottom: calc(100% + 0.4rem);
  left: 0;
  background: #ec4899;
  color: #fff;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  white-space: nowrap;
  box-shadow: 0 8px 20px -6px rgba(236, 72, 153, 0.55);
}

.comments-section {
  border-top: 1px solid var(--nf-line);
  padding-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.comment-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.comment-row {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.comment-avatar {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
  background: var(--nf-surface-2);
  color: var(--nf-accent);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.75rem;
}

.comment-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.comment-bubble {
  background: var(--nf-surface-2);
  border-radius: 12px;
  padding: 0.5rem 0.75rem;
  flex: 1;
  min-width: 0;
}

.comment-name {
  display: block;
  font-weight: 700;
  font-size: 0.8rem;
  color: var(--nf-ink);
}

.comment-content {
  margin: 0.1rem 0 0;
  font-size: 0.85rem;
  color: var(--nf-ink);
  white-space: pre-wrap;
}

.comment-time {
  font-size: 0.7rem;
  color: var(--nf-muted);
  flex-shrink: 0;
  margin-top: 0.3rem;
}

.comments-empty {
  margin: 0;
  font-size: 0.82rem;
  color: var(--nf-muted);
}

.comment-form {
  display: flex;
  gap: 0.5rem;
}

.comment-form input {
  flex: 1;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--nf-line);
  border-radius: 999px;
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
  font-size: 0.85rem;
}

.comment-form input:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.comment-form button {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
}

.comment-form button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.comment-error {
  margin: 0;
  color: #f87171;
  font-size: 0.8rem;
}
</style>
