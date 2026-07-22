<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';
import PostCard from '../../Components/PostCard.vue';

defineOptions({ layout: NewsfeedLayout });

defineProps({
  posts: Array,
});

const page = usePage();
</script>

<template>
  <div class="saved-page">
    <h2>Saved posts</h2>

    <div v-if="posts.length" class="post-list">
      <PostCard
        v-for="post in posts"
        :key="post.id"
        :post="post"
        :current-user-id="page.props.auth.user.id"
        :saved="true"
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
