<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import NewsfeedLayout from '../Layouts/NewsfeedLayout.vue';
import TurnstileWidget from '../Components/TurnstileWidget.vue';
import { timeAgo } from '../lib/date';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  posts: { type: Array, required: true },
});

const page = usePage();
const successMessage = computed(() => page.props.flash?.success ?? null);
const composerTextarea = ref(null);
const fileInput = ref(null);

const composerForm = useForm({
  content: '',
  images: [],
  captchaToken: null,
});

function onComposerFileChange(e) {
  composerForm.images = Array.from(e.target.files || []);
}

function onComposerSubmit() {
  if (!composerForm.content.trim()) {
    composerForm.setError('content', 'Please write something before submitting.');
    return;
  }
  if (composerForm.captchaToken === null) {
    composerForm.setError('captchaToken', 'Please complete the CAPTCHA challenge.');
    return;
  }

  composerForm.post(route('submissions.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      composerForm.reset();
      if (fileInput.value) fileInput.value.value = '';
    },
  });
}

function focusComposer() {
  composerTextarea.value?.focus();
  composerTextarea.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

onMounted(() => {
  if (window.location.hash === '#composer') {
    focusComposer();
  }
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

const expandedPosts = reactive({});
const overflowingPosts = reactive({});
const contentEls = {};

function registerContent(id) {
  return (el) => {
    if (el) contentEls[id] = el;
  };
}

function checkContentOverflow(id) {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const el = contentEls[id];
      if (!el || expandedPosts[id]) return;
      overflowingPosts[id] = el.scrollHeight > el.clientHeight + 2;
    });
  });
}

function toggleExpand(post) {
  expandedPosts[post.id] = !expandedPosts[post.id];
  if (!expandedPosts[post.id]) {
    nextTick(() => checkContentOverflow(post.id));
  }
}

watch(
  filteredPosts,
  (posts) => {
    nextTick(() => {
      posts.forEach((post) => checkContentOverflow(post.id));
    });
  },
  { immediate: true },
);

const lightbox = reactive({ open: false, images: [], index: 0 });

function openLightbox(post, index) {
  lightbox.images = post.image_urls;
  lightbox.index = index;
  lightbox.open = true;
}

function closeLightbox() {
  lightbox.open = false;
}

function nextImage() {
  lightbox.index = (lightbox.index + 1) % lightbox.images.length;
}

function prevImage() {
  lightbox.index = (lightbox.index - 1 + lightbox.images.length) % lightbox.images.length;
}

function onLightboxKeydown(e) {
  if (!lightbox.open) return;
  if (e.key === 'Escape') closeLightbox();
  else if (e.key === 'ArrowRight') nextImage();
  else if (e.key === 'ArrowLeft') prevImage();
}

onMounted(() => window.addEventListener('keydown', onLightboxKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onLightboxKeydown));
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

    <div id="composer" class="composer">
      <img src="/images/branding/bsufw-mark-64.png" alt="" class="composer-avatar" />
      <form class="composer-form" @submit.prevent="onComposerSubmit">
        <textarea
          ref="composerTextarea"
          v-model="composerForm.content"
          rows="2"
          placeholder="What's on your mind?"
          class="composer-textarea"
        ></textarea>

        <label class="composer-file">
          <input
            ref="fileInput"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            @change="onComposerFileChange"
          />
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l8.49-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.49a2 2 0 0 1-2.83-2.83l7.78-7.78"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
            />
          </svg>
          {{ composerForm.images.length ? `${composerForm.images.length} image${composerForm.images.length > 1 ? 's' : ''} selected` : 'Attach up to 4 images (optional)' }}
        </label>

        <ul v-if="composerForm.images.length" class="composer-file-list">
          <li v-for="image in composerForm.images" :key="`${image.name}-${image.lastModified}`">{{ image.name }}</li>
        </ul>

        <TurnstileWidget @verified="(token) => (composerForm.captchaToken = token)" />

        <p v-if="composerForm.errors.content" class="composer-banner error">{{ composerForm.errors.content }}</p>
        <p v-else-if="composerForm.errors.captchaToken" class="composer-banner error">{{ composerForm.errors.captchaToken }}</p>
        <p v-else-if="composerForm.errors.images" class="composer-banner error">{{ composerForm.errors.images }}</p>
        <p v-else-if="composerForm.errors['images.0']" class="composer-banner error">{{ composerForm.errors['images.0'] }}</p>
        <p v-if="successMessage" class="composer-banner success">{{ successMessage }}</p>

        <div class="composer-footer">
          <span class="composer-hint">Anonymous &middot; reviewed before it's posted</span>
          <button type="submit" class="composer-submit" :disabled="composerForm.processing">
            {{ composerForm.processing ? 'Posting…' : 'Post anonymously' }}
          </button>
        </div>
      </form>
    </div>

    <div class="feed-header">
      <h2>Latest Newsfeed</h2>
      <button type="button" class="start-btn" @click="focusComposer">Start a Discussion</button>
    </div>

    <p v-if="filteredPosts.length === 0" class="hint">
      {{ posts.length === 0 ? 'No approved posts yet.' : 'No posts match your search.' }}
    </p>

    <div v-else class="feed-list">
      <article v-for="post in filteredPosts" :id="`post-${post.id}`" :key="post.id" class="feed-card">
        <div class="feed-post-header">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="feed-avatar" />
          <div class="feed-header-text">
            <div class="feed-name-row">
              <span class="feed-name">BSU Freedom Wall</span>
              <svg class="feed-verified" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" title="Official page">
                <path
                  d="M9.5 12.5 11 14l4-4.5M12 3.5l1.9 1.02 2.15-.35 1.06 1.9 1.9 1.06-.35 2.15L20.5 11l-1.02 1.9.35 2.15-1.9 1.06-1.06 1.9-2.15-.35L12 20.5l-1.9-1.02-2.15.35-1.06-1.9-1.9-1.06.35-2.15L3.5 12l1.02-1.9-.35-2.15 1.9-1.06 1.06-1.9 2.15.35L12 3.5Z"
                  fill="currentColor"
                />
                <path d="m9.2 12.4 1.8 1.8 3.8-4.2" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="feed-sub-meta">
              <span class="timestamp">{{ timeAgo(post.reviewed_at) }}</span>
              <span class="dot">&middot;</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true" title="Public post">
                <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.4" />
                <path
                  d="M3.5 12h17M12 3.5a13 13 0 0 1 3.2 8.5A13 13 0 0 1 12 20.5 13 13 0 0 1 8.8 12 13 13 0 0 1 12 3.5Z"
                  stroke="currentColor"
                  stroke-width="1.4"
                />
              </svg>
            </div>
          </div>
        </div>

        <div class="feed-content-wrap">
          <p
            :ref="registerContent(post.id)"
            class="feed-content"
            :class="{ clamped: !expandedPosts[post.id] }"
          >{{ post.content }}</p>
          <button
            v-if="overflowingPosts[post.id] || expandedPosts[post.id]"
            type="button"
            class="feed-seemore"
            @click="toggleExpand(post)"
          >
            {{ expandedPosts[post.id] ? 'See less' : 'See more' }}
          </button>
        </div>

        <div v-if="post.image_urls?.length" class="feed-photo-grid" :class="`tiles-${Math.min(post.image_urls.length, 4)}`">
          <button
            v-for="(imageUrl, index) in post.image_urls.slice(0, 4)"
            :key="index"
            type="button"
            class="feed-photo-tile"
            @click="openLightbox(post, index)"
          >
            <img :src="imageUrl" alt="" />
            <span v-if="index === 3 && post.image_urls.length > 4" class="feed-photo-more">
              +{{ post.image_urls.length - 4 }}
            </span>
          </button>
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
      </article>
    </div>

    <Teleport to="body">
      <div v-if="lightbox.open" class="lightbox" @click.self="closeLightbox">
        <button type="button" class="lightbox-close" aria-label="Close" @click="closeLightbox">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>

        <button
          v-if="lightbox.images.length > 1"
          type="button"
          class="lightbox-nav prev"
          aria-label="Previous image"
          @click.stop="prevImage"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M15 5 8 12l7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>

        <img :src="lightbox.images[lightbox.index]" alt="" class="lightbox-image" />

        <button
          v-if="lightbox.images.length > 1"
          type="button"
          class="lightbox-nav next"
          aria-label="Next image"
          @click.stop="nextImage"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>

        <div v-if="lightbox.images.length > 1" class="lightbox-counter">
          {{ lightbox.index + 1 }} / {{ lightbox.images.length }}
        </div>
      </div>
    </Teleport>
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

.composer {
  display: flex;
  gap: 0.75rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 1rem;
  margin-bottom: 1.5rem;
  scroll-margin-top: 1.5rem;
}

.composer-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.composer-form {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.composer-textarea {
  width: 100%;
  padding: 0.7rem 0.85rem;
  font: inherit;
  font-size: 0.95rem;
  color: var(--nf-ink);
  background: var(--nf-surface-2);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  resize: vertical;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.composer-textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--nf-accent) 20%, transparent);
}

.composer-file {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.6rem 0.85rem;
  font-size: 0.85rem;
  color: var(--nf-muted);
  background: var(--nf-surface-2);
  border: 1px dashed var(--nf-line);
  border-radius: 10px;
  cursor: pointer;
  box-sizing: border-box;
}

.composer-file:hover {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.composer-file input[type='file'] {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
}

.composer-file-list {
  margin: -0.3rem 0 0;
  padding-left: 1.1rem;
  color: var(--nf-muted);
  font-size: 0.8rem;
}

.composer-banner {
  margin: 0;
  padding: 0.6rem 0.85rem;
  border-radius: 10px;
  font-size: 0.85rem;
}

.composer-banner.error {
  color: #f87171;
  background: rgba(220, 38, 38, 0.16);
}

.composer-banner.success {
  color: var(--status-active-fg, #4ade80);
  background: var(--status-active-bg, rgba(21, 128, 61, 0.18));
}

.composer-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.composer-hint {
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.composer-submit {
  border: none;
  border-radius: 999px;
  padding: 0.55rem 1.1rem;
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  white-space: nowrap;
}

.composer-submit:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .composer {
    flex-direction: column;
  }

  .composer-avatar {
    display: none;
  }
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
  border: none;
  text-decoration: none;
  font: inherit;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 0.5rem 0.9rem;
  border-radius: 8px;
  cursor: pointer;
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
  flex-direction: column;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 14px;
  padding: 1rem;
}

.feed-post-header {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  margin-bottom: 0.7rem;
}

.feed-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.feed-header-text {
  flex: 1;
  min-width: 0;
}

.feed-name-row {
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.feed-name {
  font-weight: 700;
  font-size: 0.92rem;
  color: var(--nf-ink);
}

.feed-verified {
  color: var(--nf-accent);
  flex-shrink: 0;
}

.feed-sub-meta {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.76rem;
  color: var(--nf-muted);
}

.feed-content-wrap {
  margin-bottom: 0.7rem;
}

.feed-content {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.5;
  color: var(--nf-ink);
  font-size: 0.92rem;
}

.feed-content.clamped {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.feed-seemore {
  display: inline-flex;
  border: none;
  background: none;
  padding: 0.3rem 0 0;
  margin: 0;
  color: var(--nf-muted);
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
}

.feed-seemore:hover {
  color: var(--nf-ink);
  text-decoration: underline;
}

.feed-photo-grid {
  display: grid;
  gap: 3px;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 0.75rem;
}

.feed-photo-grid.tiles-1 {
  grid-template-columns: 1fr;
}

.feed-photo-grid.tiles-1 .feed-photo-tile {
  aspect-ratio: 16 / 10;
}

.feed-photo-grid.tiles-2 {
  grid-template-columns: 1fr 1fr;
  height: 240px;
}

.feed-photo-grid.tiles-3 {
  grid-template-columns: 1.2fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.feed-photo-grid.tiles-3 .feed-photo-tile:first-child {
  grid-row: 1 / 3;
}

.feed-photo-grid.tiles-4 {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.feed-photo-tile {
  position: relative;
  overflow: hidden;
  background: var(--nf-surface-2);
  border: none;
  padding: 0;
  margin: 0;
  cursor: pointer;
  display: block;
  width: 100%;
  height: 100%;
}

.feed-photo-tile:hover img {
  filter: brightness(0.92);
}

.feed-photo-tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.feed-photo-more {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 700;
}

.feed-actions {
  display: flex;
  gap: 1.25rem;
  border-top: 1px solid var(--nf-line);
  padding-top: 0.6rem;
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

.lightbox {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
}

.lightbox-image {
  max-width: 90vw;
  max-height: 88vh;
  object-fit: contain;
  border-radius: 4px;
}

.lightbox-close,
.lightbox-nav {
  position: fixed;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  transition: background 0.15s ease;
}

.lightbox-close:hover,
.lightbox-nav:hover {
  background: rgba(255, 255, 255, 0.24);
}

.lightbox-close {
  top: 1.25rem;
  left: 1.25rem;
  width: 2.6rem;
  height: 2.6rem;
}

.lightbox-nav {
  top: 50%;
  transform: translateY(-50%);
  width: 3rem;
  height: 3rem;
}

.lightbox-nav.prev {
  left: 1.25rem;
}

.lightbox-nav.next {
  right: 1.25rem;
}

.lightbox-counter {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.35rem 0.9rem;
  border-radius: 999px;
}

@media (max-width: 640px) {
  .lightbox-nav {
    width: 2.4rem;
    height: 2.4rem;
  }
}
</style>
