<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const form = useForm({
  username: '',
  password: '',
});

function onSubmit() {
  form.post(route('admin.login.store'));
}
</script>

<template>
  <section>
    <h1>Admin Login</h1>
    <form @submit.prevent="onSubmit">
      <input v-model="form.username" type="text" placeholder="Username" autocomplete="username" />
      <input v-model="form.password" type="password" placeholder="Password" autocomplete="current-password" />
      <p v-if="form.errors.username" class="error">{{ form.errors.username }}</p>
      <button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </section>
</template>

<style scoped>
form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  max-width: 320px;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: var(--shadow-card);
  padding: 1.5rem;
}

input {
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--line);
  border-radius: 8px;
  font: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

button {
  background: var(--accent);
  color: #fff;
  border: none;
  padding: 0.7rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}

button:hover:not(:disabled) {
  background: var(--accent-dark);
}

button:disabled {
  opacity: 0.6;
}

.error {
  color: var(--danger);
}
</style>
