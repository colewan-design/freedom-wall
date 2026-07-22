<script setup>
import StudentLayout from '../../Layouts/StudentLayout.vue';

defineOptions({ layout: StudentLayout });

defineProps({
  profile: Object,
});
</script>

<template>
  <section class="profile-card">
    <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" class="avatar" />
    <div v-else class="avatar avatar-fallback">{{ profile.name.slice(0, 1).toUpperCase() }}</div>

    <div class="details">
      <h1>{{ profile.name }}</h1>
      <p class="username">@{{ profile.username }}</p>
      <p v-if="profile.bio" class="bio">{{ profile.bio }}</p>
      <p v-else class="bio empty">No bio yet.</p>
    </div>
  </section>
</template>

<style scoped>
.profile-card {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 2rem;
}

.avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}

.avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--nf-surface-2);
  color: var(--nf-accent);
  font-size: 1.8rem;
  font-weight: 700;
}

.details h1 {
  margin: 0;
}

.username {
  color: var(--nf-muted);
  margin: 0.15rem 0 0.75rem;
}

.bio {
  margin: 0;
  line-height: 1.5;
}

.bio.empty {
  color: var(--nf-muted);
  font-style: italic;
}
</style>
