<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FeedLayout from '../../Layouts/FeedLayout.vue';
import PostCard from '../../Components/PostCard.vue';
import { ACCEPTED_UPLOAD_TYPES, describeAttachments, validateAttachments } from '../../lib/media';

defineOptions({ layout: FeedLayout });

const props = defineProps({
  profile: Object,
  posts: Array,
  savedPostIds: Array,
  viewerReactions: Object,
  sort: String,
});

const form = useForm({ content: '', images: [] });
const fileInput = ref(null);
const attachmentSummary = computed(() => describeAttachments(form.images));
const firstName = computed(() => props.profile.name?.split(' ')[0] || 'Student');
const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
});
const greetingIcon = computed(() => new Date().getHours() < 18 ? '☀' : '☾');

async function onFileChange(event) {
  const files = Array.from(event.target.files || []);
  const error = await validateAttachments(files);
  if (error) {
    form.images = [];
    if (fileInput.value) fileInput.value.value = '';
    form.setError('images', error);
    return;
  }
  form.clearErrors('images');
  form.images = files;
}

function onSubmit() {
  if (!form.content.trim()) {
    form.setError('content', 'Please write something before posting.');
    return;
  }
  form.post(route('posts.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      if (fileInput.value) fileInput.value.value = '';
    },
  });
}

function focusComposer(prefix = '') {
  if (prefix && !form.content) form.content = prefix;
  window.requestAnimationFrame(() => document.querySelector('.composer-input')?.focus());
}

function isSaved(postId) {
  return props.savedPostIds.includes(postId);
}

function reactionFor(postId) {
  return props.viewerReactions?.[postId] ?? null;
}
</script>

<template>
  <Head title="Home" />

  <div class="feed-page">
    <section class="welcome-banner">
      <div class="welcome-copy">
        <h1><span>{{ greetingIcon }}</span> {{ greeting }}, {{ firstName }}</h1>
        <p>What’s happening at BSU today?</p>
      </div>
      <div class="welcome-motto">A Stronger<br /><b>BSU Together</b></div>
    </section>

    <form class="composer" @submit.prevent="onSubmit">
      <div class="composer-main">
        <span class="composer-avatar">
          <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" />
          <span v-else>{{ profile.name.slice(0, 1).toUpperCase() }}</span>
        </span>
        <input
          v-model="form.content"
          type="text"
          class="composer-input"
          placeholder="Share an update, ask a question, or find classmates..."
          maxlength="5000"
          @input="form.clearErrors('content')"
        />
      </div>

      <div class="composer-footer">
        <div class="composer-tools">
          <label class="composer-tool">
            <input ref="fileInput" type="file" :accept="ACCEPTED_UPLOAD_TYPES" multiple @change="onFileChange" />
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 4 4 3-3 4 4"/></svg>
            Photo
          </label>
          <button type="button" class="composer-tool" @click="focusComposer('Event: ')">
            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
            Event
          </button>
          <button type="button" class="composer-tool" @click="focusComposer('Question: ')">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.5 2.5 0 1 1 3 2.5c-.8.3-.8.9-.8 1.5M12 17h.01"/></svg>
            Question
          </button>
          <button type="button" class="composer-tool" @click="focusComposer('Looking for: ')">
            <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18"/></svg>
            Looking For
          </button>
        </div>
        <button type="submit" class="post-button" :disabled="form.processing || !form.content.trim()">
          {{ form.processing ? 'Posting...' : 'Post' }}
        </button>
      </div>

      <p v-if="form.errors.content || form.errors.images" class="composer-error">{{ form.errors.content || form.errors.images }}</p>
      <p v-if="form.images.length" class="attachment-note">{{ attachmentSummary }} attached</p>
    </form>

    <nav class="feed-tabs" aria-label="Feed filters">
      <Link href="/feed?sort=for-you" :class="{ active: !['friends', 'popular'].includes(sort) }">For You</Link>
      <Link href="/feed?sort=friends" :class="{ active: sort === 'friends' }">Following</Link>
      <a href="#communities">Communities</a>
      <a href="#events">Events</a>
      <Link href="/feed?sort=popular" :class="{ active: sort === 'popular' }">Opportunities</Link>
    </nav>

    <section v-if="posts.length" class="post-list" aria-label="Posts">
      <PostCard
        v-for="post in posts"
        :key="post.id"
        :post="post"
        :current-user-id="$page.props.auth.user.id"
        :saved="isSaved(post.id)"
        :viewer-reaction="reactionFor(post.id)"
      />
    </section>
    <section v-else class="empty-feed">
      <span>✦</span>
      <h2>Your feed is ready for its first update</h2>
      <p>Share something with the BSU community, or connect with classmates to see their posts here.</p>
      <button type="button" @click="focusComposer()">Create a post</button>
    </section>
  </div>
</template>

<style scoped>
.feed-page {
  --feed-green: #075b32;
  --feed-green-dark: #043f24;
  --feed-ink: #17211b;
  --feed-muted: #6f7d75;
  --feed-line: #dce5e0;
  padding-top: 0;
  color: var(--feed-ink);
}

.welcome-banner {
  position: relative;
  min-height: 129px;
  margin-bottom: 13px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  overflow: hidden;
  border-radius: 10px;
  background-image: linear-gradient(90deg, rgba(2, 65, 35, .93) 0%, rgba(2, 65, 35, .67) 44%, rgba(2, 65, 35, .05) 77%), url('/images/feed-campus-banner.png');
  background-position: center;
  background-size: cover;
  box-shadow: 0 5px 16px rgba(8, 51, 30, .14);
}

.welcome-copy { position: relative; z-index: 1; padding: 21px 30px; color: #fff; }
.welcome-copy h1 { margin: 0; color: #fff; font-size: clamp(23px, 2.2vw, 32px); line-height: 1.1; letter-spacing: -.7px; }
.welcome-copy h1 span { color: #ffd632; }
.welcome-copy p { margin: 7px 0 0 42px; font-size: 16px; }
.welcome-motto { position: relative; z-index: 1; margin-right: 21%; transform: rotate(-7deg); color: rgba(255,255,255,.9); font-family: 'Segoe Print', cursive; font-size: 15px; line-height: 1; text-align: center; text-shadow: 0 1px 5px rgba(0,0,0,.35); }
.welcome-motto b { font-size: 18px; }

.composer {
  margin-bottom: 7px;
  padding: 14px 16px 12px;
  border: 1px solid var(--feed-line);
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 2px 9px rgba(17,53,33,.035);
}
.composer-main { display: flex; align-items: center; gap: 13px; }
.composer-avatar { width: 46px; height: 46px; flex: 0 0 46px; display: grid; place-items: center; overflow: hidden; border-radius: 50%; background: #e8f1ec; color: var(--feed-green); font-size: 20px; font-weight: 800; }
.composer-avatar img { width: 100%; height: 100%; object-fit: cover; }
.composer-input { min-width: 0; width: 100%; height: 39px; padding: 0 14px; border: 1px solid #d9e2dd; border-radius: 7px; outline: none; background: #fff; color: var(--feed-ink); font: inherit; font-size: 12px; }
.composer-input::placeholder { color: #859089; }
.composer-input:focus { border-color: #69a989; box-shadow: 0 0 0 3px rgba(7,91,50,.08); }
.composer-footer { margin-top: 9px; padding-left: 59px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.composer-tools { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.composer-tool { position: relative; height: 29px; display: inline-flex; align-items: center; gap: 6px; padding: 0 11px; border: 0; border-radius: 20px; background: #f4f7f5; color: #3f4c45; font: inherit; font-size: 10px; cursor: pointer; }
.composer-tool:hover { background: #eaf3ee; color: var(--feed-green); }
.composer-tool svg { width: 16px; height: 16px; fill: none; stroke: #0d8a4c; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.composer-tool input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.post-button { min-width: 78px; height: 34px; border: 0; border-radius: 6px; background: linear-gradient(135deg, #08713e, #07562f); color: #fff; font-size: 11px; font-weight: 700; cursor: pointer; }
.post-button:disabled { opacity: .45; cursor: default; }
.composer-error, .attachment-note { margin: 7px 0 0 59px; font-size: 10px; }
.composer-error { color: #b42318; }
.attachment-note { color: var(--feed-green); }

.feed-tabs { height: 42px; display: flex; align-items: flex-end; gap: 9px; border-bottom: 1px solid var(--feed-line); overflow-x: auto; }
.feed-tabs a { position: relative; height: 42px; display: flex; align-items: center; padding: 0 17px; color: #536159; font-size: 11px; font-weight: 500; white-space: nowrap; text-decoration: none; }
.feed-tabs a.active { color: var(--feed-green); font-weight: 800; }
.feed-tabs a.active::after { content: ''; position: absolute; right: 10px; bottom: 0; left: 10px; height: 3px; border-radius: 3px 3px 0 0; background: var(--feed-green); }
.post-list { display: flex; flex-direction: column; gap: 9px; padding-top: 8px; }
.empty-feed { margin-top: 10px; padding: 55px 25px; border: 1px solid var(--feed-line); border-radius: 10px; background: #fff; text-align: center; }
.empty-feed > span { color: var(--feed-green); font-size: 30px; }
.empty-feed h2 { margin: 10px 0 5px; color: var(--feed-ink); font-size: 18px; }
.empty-feed p { max-width: 460px; margin: 0 auto 17px; color: var(--feed-muted); font-size: 12px; }
.empty-feed button { padding: 9px 16px; border: 0; border-radius: 6px; background: var(--feed-green); color: #fff; font-weight: 700; }

@media (max-width: 760px) {
  .welcome-banner { min-height: 116px; border-radius: 0 0 10px 10px; }
  .welcome-copy { padding: 20px; }
  .welcome-copy h1 { font-size: 23px; }
  .welcome-copy p { margin-left: 0; font-size: 13px; }
  .welcome-motto { display: none; }
  .composer-footer { padding-left: 0; align-items: flex-end; }
  .composer-tool { width: 34px; justify-content: center; padding: 0; }
  .composer-tool:not(:first-child) { width: 34px; }
  .composer-tool { font-size: 0; }
  .composer-error, .attachment-note { margin-left: 0; }
}
</style>
