<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

defineOptions({ layout: null });

defineProps({
  email: String,
});

const page = usePage();
const form = useForm({});

function resend() {
  form.post(route('verification.send'));
}

function logout() {
  router.post(route('logout'));
}
</script>

<template>
  <Head title="Verify your email" />

  <div class="auth-shell">
    <section class="card">
      <h1>Verify your email</h1>
      <p class="intro">
        Thanks for signing up! We've sent a verification link to <strong>{{ email }}</strong>.
        Click the link in that email to activate your account.
      </p>

      <p v-if="page.props.flash?.success" class="success">{{ page.props.flash.success }}</p>

      <div class="actions">
        <button type="button" :disabled="form.processing" @click="resend">
          {{ form.processing ? 'Sending…' : 'Resend verification email' }}
        </button>
        <button type="button" class="link-btn" @click="logout">Log out</button>
      </div>
    </section>
  </div>
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

.card {
  max-width: 420px;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: var(--shadow-card);
  padding: 1.5rem;
}

.intro {
  color: var(--muted);
  line-height: 1.5;
}

.success {
  color: var(--accent);
  font-weight: 600;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin-top: 1rem;
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

.link-btn {
  background: none;
  color: var(--muted);
  font-weight: 500;
  padding: 0.3rem;
}

.link-btn:hover {
  background: none;
  color: var(--ink);
}
</style>
