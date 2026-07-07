<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isActive = (path) => computed(() => page.url === path || page.url.startsWith(`${path}?`));
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <span class="brand">BSU Freedom Wall</span>
      <nav>
        <Link href="/" :class="{ active: isActive('/').value }">Submit</Link>
        <Link href="/wall" :class="{ active: isActive('/wall').value }">Wall</Link>
      </nav>
    </header>
    <main>
      <slot />
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: #0b5d2e;
  color: #fff;
}

.brand {
  font-weight: 700;
  font-size: 1.1rem;
}

.topbar nav {
  display: flex;
  gap: 1.25rem;
}

.topbar nav :deep(a) {
  color: #fff;
  text-decoration: none;
  opacity: 0.85;
}

.topbar nav :deep(a.active) {
  opacity: 1;
  border-bottom: 2px solid #ffd400;
}

main {
  flex: 1;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  padding: 1.5rem 1rem;
}
</style>
