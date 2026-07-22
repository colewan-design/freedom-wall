<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineOptions({ layout: null });

const form = useForm({
  name: '',
  username: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function onSubmit() {
  form.post(route('register.store'));
}
</script>

<template>
  <Head title="Create an account" />

  <section class="auth-shell">
    <h1>Create an account</h1>
    <form @submit.prevent="onSubmit">
      <input v-model="form.name" type="text" placeholder="Full name" autocomplete="name" />
      <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>

      <input v-model="form.username" type="text" placeholder="Username" autocomplete="username" />
      <p v-if="form.errors.username" class="error">{{ form.errors.username }}</p>

      <input v-model="form.email" type="email" placeholder="Email" autocomplete="email" />
      <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>

      <input v-model="form.password" type="password" placeholder="Password" autocomplete="new-password" />
      <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>

      <input
        v-model="form.password_confirmation"
        type="password"
        placeholder="Confirm password"
        autocomplete="new-password"
      />

      <button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Creating account…' : 'Sign up' }}
      </button>
    </form>
    <p class="switch">
      Already have an account? <Link href="/login">Log in</Link>
    </p>
  </section>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

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

.switch {
  margin-top: 1rem;
  font-size: 0.85rem;
  color: var(--muted);
}
</style>
