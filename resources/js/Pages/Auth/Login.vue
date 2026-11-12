<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';

// The same shell as the wall, threads and jobs, so signing in feels like part
// of the site rather than a separate app. Rendered in the template, not via
// defineOptions, because the page fills the layout's #rail slot.

const form = useForm({
  username: '',
  password: '',
});

const showPassword = ref(false);

function onSubmit() {
  form.post(route('login.store'), {
    onFinish: () => form.reset('password'),
  });
}

const PERKS = [
  { mark: 'JR', title: 'Job alerts', body: 'Save jobs, track applications, and get emailed when a new one matches.' },
  { mark: 'FD', title: 'Student feed', body: 'Post with your name, add friends, and message them.' },
  { mark: 'JN', title: 'Private journal', body: 'Notes only you can read.' },
];
</script>

<template>
  <Head title="Log in" />

  <DirectoryLayout hide-search>
    <div class="auth">
      <header class="hero">
        <p class="hero-badge">Student account</p>
        <h1>Welcome back</h1>
        <p class="hero-lede">
          Log in with your username, or continue with Google. New here?
          <Link href="/register">Create an account</Link> — it takes a minute.
        </p>
      </header>

      <section class="auth-card" aria-labelledby="login-head">
        <h2 id="login-head" class="sr-only">Log in</h2>

        <form class="auth-form" novalidate @submit.prevent="onSubmit">
          <label class="field">
            <span>Username</span>
            <input
              v-model="form.username"
              type="text"
              placeholder="Your username"
              autocomplete="username"
              autocapitalize="none"
              spellcheck="false"
              autofocus
              :aria-invalid="Boolean(form.errors.username)"
            />
            <small v-if="form.errors.username" class="error" role="alert">{{ form.errors.username }}</small>
          </label>

          <label class="field">
            <span>Password</span>
            <span class="password-input">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Your password"
                autocomplete="current-password"
                :aria-invalid="Boolean(form.errors.password)"
              />
              <button
                type="button"
                class="password-toggle"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                :aria-pressed="showPassword"
                @click="showPassword = !showPassword"
              >
                <svg v-if="showPassword" width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" stroke="currentColor" stroke-width="1.6" />
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
                </svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M3 3l18 18M10.6 10.7a3 3 0 0 0 4.2 4.2M9.4 5.5A9.7 9.7 0 0 1 12 5c6 0 9.5 7 9.5 7a14.8 14.8 0 0 1-3 3.9M6.2 6.9A14 14 0 0 0 2.5 12S6 19 12 19a9.6 9.6 0 0 0 3.6-.7"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                  />
                </svg>
              </button>
            </span>
            <small v-if="form.errors.password" class="error" role="alert">{{ form.errors.password }}</small>
          </label>

          <button type="submit" class="btn btn-green btn-wide" :disabled="form.processing">
            {{ form.processing ? 'Logging in…' : 'Log in' }}
            <span v-if="!form.processing" aria-hidden="true">&rarr;</span>
          </button>
        </form>

        <div class="divider"><span>or</span></div>

        <a :href="route('auth.google.redirect')" class="btn btn-plain btn-wide">
          <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.07 5.07 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1Z" fill="#4285F4" />
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.15-4.53H2.18v2.85A11 11 0 0 0 12 23Z" fill="#34A853" />
            <path d="M5.85 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.35-2.1V7.05H2.18a11 11 0 0 0 0 9.9l3.67-2.85Z" fill="#FBBC05" />
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 0 0-9.82 6.05L5.85 9.9C6.71 7.3 9.14 5.38 12 5.38Z" fill="#EA4335" />
          </svg>
          Continue with Google
        </a>

        <p class="auth-foot">
          Moderator? Use the <Link href="/admin/login">admin login</Link>.
        </p>
      </section>
    </div>

    <template #rail>
      <section class="rail-card">
        <div class="rail-head">
          <h2>What an account adds</h2>
        </div>
        <ul class="perks">
          <li v-for="perk in PERKS" :key="perk.title">
            <span class="tile tile-sm" aria-hidden="true">{{ perk.mark }}</span>
            <span>
              <b>{{ perk.title }}</b>
              <small>{{ perk.body }}</small>
            </span>
          </li>
        </ul>
      </section>

      <section class="rail-card rail-promo">
        <h2>Just want to post?</h2>
        <p>
          The wall, threads and chat need no account. Posts stay anonymous either way — logging in never puts
          your name on a wall post.
        </p>
        <Link href="/wall#composer" class="btn btn-green btn-block">
          Write an anonymous post <span aria-hidden="true">&rarr;</span>
        </Link>
      </section>
    </template>
  </DirectoryLayout>
</template>

<style scoped>
.auth {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: auth / inline-size;
}

/* --- Shared furniture ----------------------------------------------------- */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.62rem 1.1rem;
  border: 1px solid transparent;
  border-radius: var(--d-r-md);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 650;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.btn:disabled {
  opacity: 0.6;
  cursor: progress;
}

.btn-green {
  background: var(--d-green);
  color: var(--d-green-ink);
}

.btn-green:hover:not(:disabled) {
  background: var(--d-green-hover);
}

.btn-plain {
  background: var(--d-card);
  border-color: var(--d-line);
  color: var(--d-ink);
}

.btn-plain:hover {
  border-color: var(--d-line-strong);
}

.btn-wide {
  width: 100%;
  padding-block: 0.72rem;
}

.btn-block {
  width: 100%;
  margin-top: 0.9rem;
}

.tile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-weight: 800;
  line-height: 1;
}

.tile-sm {
  width: 1.65rem;
  height: 1.65rem;
  font-size: 0.62rem;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

/* --- Hero ----------------------------------------------------------------- */

.hero {
  padding: 1.5rem 0 0.5rem;
}

.hero-badge {
  display: inline-block;
  margin: 0 0 0.8rem;
  padding: 0.3rem 0.65rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  border: 1px solid var(--d-green-tint-line);
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--d-green-text);
}

.hero h1 {
  margin: 0;
  font-size: 2.4rem;
  line-height: 1.1;
  font-weight: 800;
  letter-spacing: -0.035em;
  color: var(--d-ink);
}

.hero-lede {
  margin: 0.85rem 0 0;
  max-width: 46ch;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--d-muted);
}

.hero-lede :deep(a),
.auth-foot :deep(a) {
  font-weight: 650;
  color: var(--d-green-text);
  text-decoration: none;
}

.hero-lede :deep(a:hover),
.auth-foot :deep(a:hover) {
  text-decoration: underline;
}

/* --- Card ----------------------------------------------------------------- */

.auth-card {
  width: 100%;
  max-width: 28rem;
  padding: 1.4rem 1.4rem 1.2rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 0.95rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.field > span:first-child {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.field input {
  width: 100%;
  min-width: 0;
  padding: 0.62rem 0.75rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.9rem;
  transition: border-color 0.15s ease;
}

.field input::placeholder {
  color: var(--d-faint);
}

.field input:focus {
  outline: none;
  border-color: var(--d-green);
}

.field input[aria-invalid='true'] {
  border-color: #dc2626;
}

.password-input {
  position: relative;
  display: flex;
}

.password-input input {
  padding-right: 2.6rem;
}

.password-toggle {
  position: absolute;
  top: 50%;
  right: 0.45rem;
  display: inline-flex;
  padding: 0.3rem;
  transform: translateY(-50%);
  border: 0;
  border-radius: var(--d-r-sm);
  background: none;
  color: var(--d-muted);
  cursor: pointer;
}

.password-toggle:hover {
  color: var(--d-ink);
}

.error {
  font-size: 0.78rem;
  color: #dc2626;
}

.divider {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 1.1rem 0;
  font-size: 0.74rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--d-line);
}

.auth-foot {
  margin: 1.1rem 0 0;
  padding-top: 0.9rem;
  border-top: 1px dashed var(--d-line);
  font-size: 0.8rem;
  color: var(--d-muted);
  text-align: center;
}

/* --- Rail ----------------------------------------------------------------- */

.rail-card {
  padding: 0.9rem 0.95rem 1rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.rail-head {
  margin-bottom: 0.6rem;
}

.rail-head h2,
.rail-promo h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-promo p {
  margin: 0.5rem 0 0;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.perks {
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.perks li {
  display: flex;
  gap: 0.6rem;
}

.perks b {
  display: block;
  font-size: 0.84rem;
  font-weight: 650;
  color: var(--d-ink);
}

.perks small {
  display: block;
  margin-top: 0.1rem;
  font-size: 0.76rem;
  line-height: 1.45;
  color: var(--d-muted);
}

/* --- Responsive ----------------------------------------------------------- */

@container auth (max-width: 540px) {
  .hero h1 {
    font-size: 2rem;
  }

  .auth-card {
    max-width: none;
    padding: 1.15rem 1.05rem 1rem;
  }
}
</style>
