<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isActive = (path) => computed(() => page.url === path || page.url.startsWith(`${path}?`));
const isWidePage = computed(() => page.component?.startsWith('Admin/'));
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <span class="brand">
        <span class="brand-mark">FW</span>
        BSU Freedom Wall
      </span>
      <nav>
        <Link href="/" :class="{ active: isActive('/').value }">Submit</Link>
        <Link href="/wall" :class="{ active: isActive('/wall').value }">Wall</Link>
      </nav>
    </header>
    <main :class="{ wide: isWidePage }">
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
  padding: 0.9rem 1.5rem;
  background: var(--paper);
  border-bottom: 1px solid var(--line);
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--ink);
}

.brand-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 9px;
  background: var(--accent);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
}

.topbar nav {
  display: flex;
  gap: 1.5rem;
}

.topbar nav :deep(a) {
  color: var(--muted);
  text-decoration: none;
  font-weight: 600;
  font-size: 0.92rem;
  padding-bottom: 0.3rem;
  border-bottom: 2px solid transparent;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.topbar nav :deep(a:hover) {
  color: var(--ink);
}

.topbar nav :deep(a.active) {
  color: var(--ink);
  border-bottom-color: var(--accent);
}

main {
  flex: 1;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  padding: 1.5rem 1rem;
}

main.wide {
  max-width: min(1560px, calc(100vw - 2rem));
  padding-inline: 1.5rem;
}
</style>
