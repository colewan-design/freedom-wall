<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import StudentLayout from '../../Layouts/StudentLayout.vue';
import PostCard from '../../Components/PostCard.vue';

defineOptions({ layout: StudentLayout });

const props = defineProps({
  posts: Array,
  viewerReactions: Object,
});

const page = usePage();

function reactionFor(postId) {
  return props.viewerReactions?.[postId] ?? null;
}
</script>

<template>
  <div class="saved-page">
    <h2>Saved posts</h2>

    <div v-if="posts.length" class="post-list">
      <PostCard
        v-for="(post, index) in posts"
        :key="post.id"
        :post="post"
        :current-user-id="page.props.auth.user.id"
        :saved="true"
        :viewer-reaction="reactionFor(post.id)"
        :tint="index % 2 === 0 ? 'blue' : 'cream'"
      />
    </div>
    <p v-else class="empty">
      Nothing saved yet — tap "Save" on a post in your <Link href="/feed">Feed</Link> to keep it here.
    </p>
  </div>
</template>

<style scoped>
.saved-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.saved-page h2 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--nf-ink);
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
