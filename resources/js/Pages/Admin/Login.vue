<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineOptions({ layout: null });

const year = new Date().getFullYear();

const form = useForm({
  username: '',
  password: '',
});

function onSubmit() {
  form.post(route('admin.login.store'));
}
</script>

<template>
  <Head title="Admin Login" />

  <div class="login-shell">
    <aside class="login-hero">
      <svg class="hero-arcs" viewBox="0 0 400 400" fill="none" aria-hidden="true">
        <path d="M400 40C260 40 140 160 140 300" stroke="white" stroke-opacity="0.14" stroke-width="1.5" />
        <path d="M400 90C285 90 190 190 190 320" stroke="white" stroke-opacity="0.14" stroke-width="1.5" />
        <path d="M400 140C310 140 240 220 240 340" stroke="white" stroke-opacity="0.14" stroke-width="1.5" />
      </svg>

      <img src="/images/branding/bsufw-mark-64.png" alt="" class="hero-mark" />

      <div class="hero-copy">
        <h1>Hello,<br />Moderator! <span class="wave">👋</span></h1>
        <p>Review anonymous submissions and keep the wall moving — approve, reject, and post to the feed in seconds.</p>
      </div>

      <p class="hero-footer">&copy; {{ year }} BSU Freedom Wall. All rights reserved.</p>
    </aside>

    <main class="login-panel">
      <span class="login-brand">BSU Freedom Wall</span>

      <div class="login-form-wrap">
        <h2>Welcome Back!</h2>
        <p class="login-subtext">Moderator access only — sign in with your admin credentials.</p>

        <form @submit.prevent="onSubmit">
          <label class="field">
            <span class="field-label">Username</span>
            <input v-model="form.username" type="text" placeholder="Username" autocomplete="username" autofocus />
          </label>

          <label class="field">
            <span class="field-label">Password</span>
            <input v-model="form.password" type="password" placeholder="Password" autocomplete="current-password" />
          </label>

          <p v-if="form.errors.username" class="error">{{ form.errors.username }}</p>

          <button type="submit" class="submit-btn" :disabled="form.processing">
            {{ form.processing ? 'Signing in…' : 'Login Now' }}
          </button>
        </form>
      </div>
    </main>
  </div>
</template>

<style scoped>
.login-shell {
  display: flex;
  min-height: 100vh;
  background: #fff;
}

.login-hero {
  position: relative;
  overflow: hidden;
  flex: 0 0 44%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 3rem;
  background: linear-gradient(150deg, #042f2a 0%, #0f766e 45%, #0d9488 100%);
  color: #fff;
}

.hero-arcs {
  position: absolute;
  top: 0;
  right: 0;
  width: 60%;
  height: auto;
  pointer-events: none;
}

.hero-mark {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 10px;
  object-fit: cover;
  position: relative;
  z-index: 1;
}

.hero-copy {
  position: relative;
  z-index: 1;
}

.hero-copy h1 {
  margin: 0 0 1rem;
  font-size: 2.6rem;
  font-weight: 800;
  line-height: 1.15;
}

.wave {
  display: inline-block;
  animation: wave 2.4s ease infinite;
  transform-origin: 70% 70%;
}

@keyframes wave {
  0%, 60%, 100% {
    transform: rotate(0deg);
  }
  10% {
    transform: rotate(14deg);
  }
  20% {
    transform: rotate(-8deg);
  }
  30% {
    transform: rotate(14deg);
  }
  40% {
    transform: rotate(-4deg);
  }
}

.hero-copy p {
  margin: 0;
  max-width: 34ch;
  font-size: 1rem;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.82);
}

.hero-footer {
  position: relative;
  z-index: 1;
  margin: 0;
  font-size: 0.82rem;
  color: rgba(255, 255, 255, 0.6);
}

.login-panel {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 3rem;
}

.login-brand {
  font-weight: 800;
  font-size: 1.05rem;
  color: var(--ink);
}

.login-form-wrap {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  max-width: 360px;
  width: 100%;
  margin: 0 auto;
}

.login-form-wrap h2 {
  margin: 0 0 0.4rem;
  font-size: 1.9rem;
  font-weight: 800;
  color: var(--ink);
}

.login-subtext {
  margin: 0 0 2rem;
  color: var(--muted);
  font-size: 0.92rem;
  line-height: 1.5;
}

form {
  display: flex;
  flex-direction: column;
  gap: 1.4rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.field-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--muted);
}

.field input {
  padding: 0.5rem 0.1rem;
  border: none;
  border-bottom: 1.5px solid var(--line);
  border-radius: 0;
  background: transparent;
  font: inherit;
  font-size: 0.98rem;
  color: var(--ink);
  transition: border-color 0.15s ease;
}

.field input:focus {
  outline: none;
  border-bottom-color: var(--accent);
}

.submit-btn {
  margin-top: 0.5rem;
  background: #16181d;
  color: #fff;
  border: none;
  padding: 0.85rem;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
}

.submit-btn:hover:not(:disabled) {
  background: #000;
}

.submit-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  margin: -0.6rem 0 0;
  color: var(--danger);
  font-size: 0.85rem;
}

@media (max-width: 780px) {
  .login-hero {
    display: none;
  }

  .login-panel {
    padding: 2rem 1.5rem;
  }
}
</style>
