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
}

input {
  padding: 0.6rem;
  border: 1px solid #ccc;
  border-radius: 6px;
  font: inherit;
}

button {
  background: #0b5d2e;
  color: #fff;
  border: none;
  padding: 0.7rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
}

.error {
  color: #c0392b;
}
</style>
