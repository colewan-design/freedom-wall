<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import StudentLayout from '../../Layouts/StudentLayout.vue';
import PostCard from '../../Components/PostCard.vue';

defineOptions({ layout: StudentLayout });

const props = defineProps({
  profile: Object,
  posts: Array,
  savedPostIds: Array,
});

const form = useForm({
  content: '',
  images: [],
});

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
    onSuccess: () => form.reset(),
  });
}

function isSaved(postId) {
  return props.savedPostIds.includes(postId);
}
</script>

<template>
  <div class="feed">
    <form class="composer-bar" @submit.prevent="onSubmit">
      <div class="composer-row">
        <span class="composer-avatar">
          <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" />
          <span v-else>{{ profile.name.slice(0, 1).toUpperCase() }}</span>
        </span>
        <input
          v-model="form.content"
          type="text"
          class="composer-input"
          placeholder="Share something…"
        />
        <button type="submit" class="composer-send" :disabled="form.processing || !form.content.trim()">
          {{ form.processing ? 'Posting…' : 'Send' }}
        </button>
      </div>

      <p v-if="form.errors.content" class="composer-error">{{ form.errors.content }}</p>
      <p v-else-if="form.errors.images" class="composer-error">{{ form.errors.images }}</p>
      <p v-if="form.images.length" class="composer-file-count">
        {{ form.images.length }} image{{ form.images.length > 1 ? 's' : '' }} attached
      </p>

      <div class="composer-actions">
        <label class="composer-action">
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple @change="onFileChange" />
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="3" y="4" width="18" height="16" rx="3" stroke="currentColor" stroke-width="1.6" />
            <circle cx="8.5" cy="10" r="1.6" fill="currentColor" />
            <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Image
        </label>

        <span class="composer-action inert" title="Coming soon">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
          File
        </span>

        <span class="composer-action inert" title="Coming soon">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M12 21s-7-6-7-11.2A7 7 0 0 1 12 3a7 7 0 0 1 7 6.8C19 15 12 21 12 21Z"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linejoin="round"
            />
            <circle cx="12" cy="10" r="2.2" stroke="currentColor" stroke-width="1.6" />
          </svg>
          Location
        </span>

        <span class="composer-visibility">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.6" />
            <path d="M3 19c0-3 2.7-5 6-5s6 2 6 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.6" />
            <path d="M15.5 19c.2-2.2 1.8-3.7 4-3.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
          Friends
        </span>
      </div>
    </form>

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
  </div>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.composer-bar {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.composer-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
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

.composer-input {
  flex: 1;
  border: 1px solid var(--nf-line);
  background: var(--nf-surface-2);
  border-radius: 999px;
  padding: 0.6rem 1rem;
  color: var(--nf-ink);
  font: inherit;
}

.composer-input:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.composer-input::placeholder {
  color: var(--nf-muted);
}

.composer-send {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
  flex-shrink: 0;
}

.composer-send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.composer-error {
  margin: 0;
  color: #f87171;
  font-size: 0.82rem;
}

.composer-file-count {
  margin: 0;
  font-size: 0.8rem;
  color: var(--nf-muted);
}

.composer-actions {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  border-top: 1px solid var(--nf-line);
  padding-top: 0.6rem;
}

.composer-action {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  color: var(--nf-muted);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  position: relative;
}

.composer-action input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

.composer-action.inert {
  cursor: default;
  opacity: 0.55;
}

.composer-visibility {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-left: auto;
  color: var(--nf-muted);
  font-size: 0.82rem;
  font-weight: 600;
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
</style>
