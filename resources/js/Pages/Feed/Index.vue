<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';
import PostCard from '../../Components/PostCard.vue';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  profile: Object,
  posts: Array,
  savedPostIds: Array,
});

const composerOpen = ref(false);
const composerTextarea = ref(null);
const fileInput = ref(null);

const form = useForm({
  content: '',
  images: [],
});

function openComposer() {
  composerOpen.value = true;
  nextTick(() => composerTextarea.value?.focus());
}

function closeComposer() {
  composerOpen.value = false;
}

function onFileChange(e) {
  form.images = Array.from(e.target.files || []);
}

function onSubmit() {
  if (!form.content.trim()) {
    form.setError('content', 'Please write something before posting.');
    return;
  }

  form.post(route('posts.store'), {
    forceFormData: true,
    onSuccess: () => {
      form.reset();
      if (fileInput.value) fileInput.value.value = '';
      closeComposer();
    },
  });
}

function isSaved(postId) {
  return props.savedPostIds.includes(postId);
}
</script>

<template>
  <div class="feed">
    <button type="button" class="composer-trigger" @click="openComposer">
      <span class="composer-avatar">
        <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" />
        <span v-else>{{ profile.name.slice(0, 1).toUpperCase() }}</span>
      </span>
      <span class="composer-trigger-text">What's on your mind, {{ profile.name.split(' ')[0] }}?</span>
    </button>

    <div v-if="posts.length" class="post-list">
      <PostCard
        v-for="post in posts"
        :key="post.id"
        :post="post"
        :current-user-id="$page.props.auth.user.id"
        :saved="isSaved(post.id)"
      />
    </div>
    <p v-else class="empty">
      No posts yet. Add friends from the <Link href="/friends">Friends</Link> page to see their posts here.
    </p>

    <Teleport to="body">
      <div v-if="composerOpen" class="composer-modal-overlay" @click.self="closeComposer">
        <div class="composer-modal">
          <div class="composer-modal-header">
            <h3>Create post</h3>
            <button type="button" class="composer-modal-close" aria-label="Close" @click="closeComposer">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </button>
          </div>

          <form class="composer-form" @submit.prevent="onSubmit">
            <textarea
              ref="composerTextarea"
              v-model="form.content"
              rows="5"
              placeholder="What's on your mind?"
              class="composer-textarea"
            ></textarea>

            <label class="composer-attach-row">
              <span>
                {{ form.images.length ? `${form.images.length} image${form.images.length > 1 ? 's' : ''} selected` : 'Add photos' }}
              </span>
              <span class="composer-attach-icon">
                <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="onFileChange" />
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="8.5" cy="10" r="1.6" fill="currentColor" />
                  <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </label>

            <p v-if="form.errors.content" class="composer-error">{{ form.errors.content }}</p>
            <p v-else-if="form.errors.images" class="composer-error">{{ form.errors.images }}</p>

            <button type="submit" class="composer-submit" :disabled="form.processing || !form.content.trim()">
              {{ form.processing ? 'Posting…' : 'Post' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.composer-trigger {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 0.85rem 1rem;
  cursor: pointer;
  text-align: left;
}

.composer-trigger:hover {
  border-color: var(--nf-accent);
}

.composer-avatar {
  width: 2.4rem;
  height: 2.4rem;
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

.composer-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.composer-trigger-text {
  color: var(--nf-muted);
  font-size: 0.92rem;
}

.post-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.empty {
  color: var(--nf-muted);
  font-size: 0.9rem;
}

.empty :deep(a) {
  color: var(--nf-accent);
  font-weight: 600;
}

.composer-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 4vh 1rem;
  overflow-y: auto;
}

.composer-modal {
  width: 100%;
  max-width: 500px;
  background: var(--nf-panel, #1f2027);
  border: 1px solid var(--nf-line, #2c2d36);
  border-radius: 12px;
  padding: 0.5rem 1rem 1rem;
}

.composer-modal-header {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--nf-line, #2c2d36);
  margin-bottom: 0.9rem;
}

.composer-modal-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--nf-ink, #e9e9ee);
}

.composer-modal-close {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 50%;
  border: none;
  background: var(--nf-surface-2, #2a2b33);
  color: var(--nf-ink, #e9e9ee);
  cursor: pointer;
}

.composer-modal-close:hover {
  background: var(--nf-line, #2c2d36);
}

.composer-form {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.composer-textarea {
  width: 100%;
  min-height: 7rem;
  padding: 0;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 1.1rem;
  color: var(--nf-ink, #e9e9ee);
  resize: vertical;
}

.composer-textarea:focus {
  outline: none;
}

.composer-textarea::placeholder {
  color: var(--nf-muted, #9497a6);
}

.composer-attach-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.6rem 0.85rem;
  border: 1px solid var(--nf-line, #2c2d36);
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--nf-ink, #e9e9ee);
  cursor: pointer;
}

.composer-attach-row:hover {
  border-color: var(--nf-accent, #0d9488);
}

.composer-attach-icon {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  color: var(--nf-accent, #0d9488);
  flex-shrink: 0;
}

.composer-attach-icon input[type='file'] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.composer-error {
  margin: 0;
  color: #f87171;
  font-size: 0.85rem;
}

.composer-submit {
  width: 100%;
  border: none;
  border-radius: 8px;
  padding: 0.65rem 1rem;
  background: var(--nf-accent, #0d9488);
  color: var(--nf-accent-contrast, #fff);
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
}

.composer-submit:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
