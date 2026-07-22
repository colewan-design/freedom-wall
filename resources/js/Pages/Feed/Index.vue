<script setup>
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';

defineOptions({ layout: NewsfeedLayout });

defineProps({
  profile: Object,
});
</script>

<template>
  <section class="feed-placeholder">
    <h1>Welcome, {{ profile.name }}</h1>
    <p>Your friends-only feed is coming soon. For now, this confirms your account is set up.</p>
  </section>
</template>

<style scoped>
.feed-placeholder {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 2rem;
}

.feed-placeholder h1 {
  margin: 0 0 0.5rem;
}

.feed-placeholder p {
  color: var(--nf-muted);
  margin: 0;
}
</style>
