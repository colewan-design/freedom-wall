<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthHero from '../../Components/AuthHero.vue';

defineOptions({ layout: null });

const form = useForm({
  username: '',
  password: '',
});

const showPassword = ref(false);

function onSubmit() {
  form.post(route('login.store'));
}
</script>

<template>
  <Head title="Log in" />

  <div class="auth-shell">
    <AuthHero />

    <main class="auth-panel">
      <div class="auth-form-wrap">
        <h2>Welcome back</h2>
        <p class="auth-subtext">New here? <Link href="/register">Create an account</Link></p>

        <form @submit.prevent="onSubmit">
          <label class="field">
            <span class="field-label">Username</span>
            <input v-model="form.username" type="text" placeholder="Username" autocomplete="username" autofocus />
          </label>
          <p v-if="form.errors.username" class="error">{{ form.errors.username }}</p>

          <label class="field">
            <span class="field-label">Password</span>
            <div class="password-input">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter your password"
                autocomplete="current-password"
              />
              <button type="button" class="password-toggle" @click="showPassword = !showPassword">
                <svg v-if="showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                  />
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M3 3l18 18M10.6 10.7a3 3 0 0 0 4.2 4.2M9.4 5.5A9.7 9.7 0 0 1 12 5c6 0 9.5 7 9.5 7a14.8 14.8 0 0 1-3 3.9M6.2 6.9A14 14 0 0 0 2.5 12S6 19 12 19a9.6 9.6 0 0 0 3.6-.7"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                  />
                </svg>
              </button>
            </div>
          </label>

          <button type="submit" class="submit-btn" :disabled="form.processing">
            {{ form.processing ? 'Signing in…' : 'Log in' }}
          </button>
        </form>

        <div class="divider"><span>Or continue with</span></div>

        <a :href="route('auth.google.redirect')" class="google-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
            <path
              d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.07 5.07 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1Z"
              fill="#4285F4"
            />
            <path
              d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.15-4.53H2.18v2.85A11 11 0 0 0 12 23Z"
              fill="#34A853"
            />
            <path
              d="M5.85 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.35-2.1V7.05H2.18a11 11 0 0 0 0 9.9l3.67-2.85Z"
              fill="#FBBC05"
            />
            <path
              d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 0 0-9.82 6.05L5.85 9.9C6.71 7.3 9.14 5.38 12 5.38Z"
              fill="#EA4335"
            />
          </svg>
          Google
        </a>
      </div>
    </main>
  </div>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  background: #0f1115;
}

.auth-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1.5rem;
}

.auth-form-wrap {
  width: 100%;
  max-width: 380px;
}

.auth-form-wrap h2 {
  margin: 0 0 0.4rem;
  font-size: 2rem;
  font-weight: 800;
  color: #f2f3f5;
}

.auth-subtext {
  margin: 0 0 1.75rem;
  color: #9497a6;
  font-size: 0.92rem;
}

.auth-subtext :deep(a) {
  color: #2dd4bf;
  font-weight: 600;
  text-decoration: none;
}

form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field-label {
  font-size: 0.78rem;
  font-weight: 600;
  color: #9497a6;
}

input {
  padding: 0.7rem 0.85rem;
  border: 1px solid #2c2d36;
  border-radius: 10px;
  background: #1c1e25;
  color: #f2f3f5;
  font: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

input::placeholder {
  color: #6b6f7d;
}

input:focus {
  outline: none;
  border-color: #0d9488;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.25);
}

.password-input {
  position: relative;
  display: flex;
}

.password-input input {
  flex: 1;
  padding-right: 2.5rem;
}

.password-toggle {
  position: absolute;
  right: 0.6rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #9497a6;
  cursor: pointer;
  display: inline-flex;
  padding: 0.2rem;
}

.submit-btn {
  margin-top: 0.25rem;
  background: #0d9488;
  color: #fff;
  border: none;
  padding: 0.8rem;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background 0.15s ease;
}

.submit-btn:hover:not(:disabled) {
  background: #0f766e;
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  margin: -0.5rem 0 0;
  color: #f87171;
  font-size: 0.8rem;
}

.divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 1.4rem 0 1.1rem;
  color: #6b6f7d;
  font-size: 0.8rem;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #2c2d36;
}

.google-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  width: 100%;
  border: 1px solid #2c2d36;
  border-radius: 10px;
  padding: 0.7rem;
  background: #1c1e25;
  color: #f2f3f5;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.92rem;
  transition: border-color 0.15s ease;
}

.google-btn:hover {
  border-color: #4b4d59;
}

@media (max-width: 900px) {
  .auth-panel {
    padding: 2rem 1.25rem;
  }
}
</style>
