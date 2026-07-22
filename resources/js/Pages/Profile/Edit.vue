<script setup>
import { useForm } from '@inertiajs/vue3';
import StudentLayout from '../../Layouts/StudentLayout.vue';

defineOptions({ layout: StudentLayout });

const props = defineProps({
  profile: Object,
});

const form = useForm({
  name: props.profile.name,
  bio: props.profile.bio ?? '',
  avatar: null,
});

function onAvatarChange(event) {
  form.avatar = event.target.files[0] ?? null;
}

function onSubmit() {
  form.post(route('profile.update'), {
    forceFormData: true,
    onSuccess: () => {
      form.avatar = null;
    },
  });
}
</script>

<template>
  <section class="edit-card">
    <h1>Edit profile</h1>

    <div v-if="profile.avatar_url" class="current-avatar">
      <img :src="profile.avatar_url" alt="" />
    </div>

    <form @submit.prevent="onSubmit">
      <label>
        Name
        <input v-model="form.name" type="text" />
      </label>
      <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>

      <label>
        Bio
        <textarea v-model="form.bio" rows="4"></textarea>
      </label>
      <p v-if="form.errors.bio" class="error">{{ form.errors.bio }}</p>

      <label>
        Avatar
        <input type="file" accept="image/*" @change="onAvatarChange" />
      </label>
      <p v-if="form.errors.avatar" class="error">{{ form.errors.avatar }}</p>

      <button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Saving…' : 'Save changes' }}
      </button>
    </form>
  </section>
</template>

<style scoped>
.edit-card {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 2rem;
  max-width: 480px;
}

.current-avatar img {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 1rem;
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: var(--nf-muted);
}

input,
textarea {
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--nf-line);
  border-radius: 8px;
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
}

button {
  background: var(--nf-accent);
  color: #fff;
  border: none;
  padding: 0.7rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
}

.error {
  color: var(--danger, #d9534f);
  font-size: 0.8rem;
  margin: 0;
}
</style>
