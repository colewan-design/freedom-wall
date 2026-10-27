<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Bump this when the copy below changes so a dismissed notice comes back for
// everyone instead of staying hidden behind the old dismissal.
const NOTICE_KEY = 'wall-notice-storm-2026-10';

// Per tab. sessionStorage means the notice returns on the next visit but does
// not nag on every Inertia navigation. Swap to localStorage for once-per-device.
const store = () => {
  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
};

const page = usePage();

// Moderators working the queue do not need the public announcement.
const suppressed = computed(() => (page.url ?? '').startsWith('/admin'));

const open = ref(false);
const dismissButton = ref(null);

function dismiss() {
  open.value = false;
  document.body.style.overflow = '';
  store()?.setItem(NOTICE_KEY, '1');
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault();
    dismiss();
  }
}

onMounted(() => {
  if (suppressed.value || store()?.getItem(NOTICE_KEY) === '1') return;

  open.value = true;
  document.body.style.overflow = 'hidden';
  document.addEventListener('keydown', onKeydown);
  requestAnimationFrame(() => dismissButton.value?.focus());
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});
</script>

<template>
  <div
    v-if="open"
    class="notice-overlay"
    @click.self="dismiss"
  >
    <div
      class="notice-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="site-notice-title"
    >
      <p class="notice-label">01 — Service notice</p>

      <h2 id="site-notice-title" class="notice-title">
        We're pausing for a bit
      </h2>

      <p class="notice-lead">
        Our Facebook page and this website are temporarily unavailable. We're
        letting the storm pass first.
      </p>

      <ul class="notice-list">
        <li>A wave of mass reports has been detected against the page.</li>
        <li>A lot of bashers have been posting — more than moderation can keep up with.</li>
        <li>Parts of the site may be offline, slow, or closed to new posts meanwhile.</li>
      </ul>

      <p class="notice-foot">
        Thanks for your patience. We'll post an update once things settle down.
        Stay kind out there.
      </p>

      <button
        ref="dismissButton"
        type="button"
        class="notice-button"
        @click="dismiss"
      >
        I understand
      </button>
    </div>
  </div>
</template>

<style scoped>
/*
 * Palette tokens (--b-*) are declared at :root in app.css, so this resolves
 * on every page — layouts, the standalone auth screens, and the admin login.
 */

.notice-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 6vh 1rem 2rem;
  overflow-y: auto;
  background: var(--b-scrim, rgba(10, 10, 10, 0.3));
  backdrop-filter: blur(10px);
}

.notice-card {
  width: 100%;
  max-width: 30rem;
  padding: 1.75rem;
  background: var(--b-bg, #fff);
  border: 1px solid var(--b-200, #e9e9e9);
  border-radius: var(--b-r-card, 0);
  box-shadow: var(--b-shadow-modal, none);
  font-family: var(--b-sans);
  color: var(--b-ink, #0a0a0a);
  animation: notice-in 0.2s var(--b-ease, ease-out);
}

@keyframes notice-in {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.notice-label {
  margin: 0 0 0.75rem;
  font-family: var(--b-display);
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.notice-title {
  margin: 0 0 0.85rem;
  font-size: 1.35rem;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.01em;
}

.notice-lead {
  margin: 0 0 1.1rem;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--b-700, #404040);
}

.notice-list {
  margin: 0 0 1.25rem;
  padding: 1rem 0 0;
  list-style: none;
  border-top: 1px solid var(--b-200, #e9e9e9);
  display: grid;
  gap: 0.6rem;
}

.notice-list li {
  position: relative;
  padding-left: 1.1rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--b-500, #737373);
}

.notice-list li::before {
  content: '';
  position: absolute;
  top: 0.58rem;
  left: 0;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--b-dot, rgba(10, 10, 10, 0.9));
}

.notice-foot {
  margin: 0 0 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--b-200, #e9e9e9);
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--b-500, #737373);
}

.notice-button {
  width: 100%;
  border: none;
  border-radius: var(--b-r-input, 0);
  padding: 0.7rem 1rem;
  background: var(--b-green, #0a4a17);
  color: var(--b-green-ink, #fff);
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  cursor: pointer;
  transition: background 0.2s ease;
}

.notice-button:hover {
  background: var(--b-green-hover, #146c26);
}

.notice-button:focus-visible {
  outline: 2px solid var(--b-ink, #0a0a0a);
  outline-offset: 2px;
}

@media (max-width: 480px) {
  .notice-overlay {
    padding: 4vh 0.75rem 1.5rem;
  }

  .notice-card {
    padding: 1.35rem;
  }
}
</style>
