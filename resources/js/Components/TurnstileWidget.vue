<script setup>
import { onMounted, ref } from 'vue';

const emit = defineEmits(['verified']);
const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY;
const container = ref(null);

function loadScript() {
  return new Promise((resolve, reject) => {
    if (window.turnstile) return resolve();
    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    script.async = true;
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

onMounted(async () => {
  if (!siteKey) {
    // No site key configured (local dev) — skip CAPTCHA, backend also skips verification.
    emit('verified', '');
    return;
  }
  await loadScript();
  window.turnstile.render(container.value, {
    sitekey: siteKey,
    callback: (token) => emit('verified', token),
  });
});
</script>

<template>
  <div v-if="siteKey" ref="container" class="turnstile"></div>
</template>
