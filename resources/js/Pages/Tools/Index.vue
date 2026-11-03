<script setup>
import { Head, Link } from '@inertiajs/vue3';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';
import { EXTERNAL_APPS, SITE_APPS } from '../../lib/apps';

defineOptions({ layout: NewsfeedLayout });

// Apps on this site go through Inertia so the shell does not reload. Everything
// else is a plain anchor into a new tab.
const decorate = (apps) =>
  apps.map((app) => ({
    ...app,
    component: app.internal ? Link : 'a',
    attrs: app.internal
      ? { href: app.url }
      : { href: app.url, target: '_blank', rel: 'noopener noreferrer' },
  }));

// The list is static, so number the sections here rather than hard-coding a
// heading that goes wrong the day one of the groups is empty.
const GROUPS = [
  {
    id: 'here',
    title: 'On this site',
    note: null,
    apps: decorate(SITE_APPS),
  },
  {
    id: 'elsewhere',
    title: 'Elsewhere on the web',
    note: 'These open in a new tab.',
    apps: decorate(EXTERNAL_APPS),
  },
].filter((group) => group.apps.length);
</script>

<template>
  <Head title="Tools" />

  <div class="tools">
    <header class="tools-hero">
      <div class="tools-hero-copy">
        <span class="tools-kicker">04 — Tools</span>
        <h1>Stuff we built</h1>
        <p>
          Side projects from the same people who run the wall. All free, all
          open to anyone — no student account needed.
        </p>
      </div>
      <blockquote>
        “Built on campus.<br />Shared with everyone.”
        <cite>— BSU Freedom Wall</cite>
      </blockquote>
    </header>

    <section
      v-for="(group, index) in GROUPS"
      :key="group.id"
      class="tools-section"
      :aria-labelledby="`tools-${group.id}`"
    >
      <h2 :id="`tools-${group.id}`">{{ index + 1 }} — {{ group.title }}</h2>
      <p v-if="group.note" class="tools-note">{{ group.note }}</p>

      <ul class="tools-grid">
        <li v-for="app in group.apps" :key="app.id">
          <component :is="app.component" v-bind="app.attrs" class="tools-card">
            <span class="tools-mark" aria-hidden="true">{{ app.mark }}</span>

            <span class="tools-card-head">
              <strong>{{ app.name }}</strong>
              <span class="tools-platform">{{ app.platform }}</span>
            </span>

            <span class="tools-blurb">{{ app.blurb }}</span>
            <span class="tools-desc">{{ app.description }}</span>

            <span class="tools-tags">
              <span v-for="tag in app.tags" :key="tag">{{ tag }}</span>
            </span>

            <span class="tools-foot">
              <span class="tools-host">{{ app.host }}</span>
              <span class="tools-go" aria-hidden="true">&rarr;</span>
            </span>
          </component>
        </li>
      </ul>
    </section>

    <footer class="tools-footer">
      <p>
        Something broken, or an idea for the next one? Say so on
        <Link href="/threads">Threads</Link> — we read everything there.
      </p>
    </footer>
  </div>
</template>

<style scoped>
.tools {
  display: grid;
  gap: 2rem;
  padding: 1.5rem 0 3rem;
  font-family: var(--b-sans);
  color: var(--b-ink);
}

/* Hero — same shape as the ID Check page so the two read as a pair. */
.tools-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 2rem;
  align-items: end;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--b-200);
}

.tools-kicker {
  display: block;
  margin-bottom: 0.65rem;
  font-family: var(--b-display);
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-green-text);
}

.tools-hero h1 {
  margin: 0 0 0.6rem;
  font-size: clamp(1.6rem, 4vw, 2.4rem);
  font-weight: 600;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.tools-hero p {
  margin: 0;
  max-width: 46ch;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--b-500);
}

.tools-hero blockquote {
  margin: 0;
  padding-left: 1.25rem;
  border-left: 2px solid var(--b-green);
  font-family: var(--b-display);
  font-size: 0.8rem;
  line-height: 1.8;
  text-align: right;
  color: var(--b-700);
}

.tools-hero cite {
  display: block;
  margin-top: 0.5rem;
  font-family: var(--b-mono);
  font-size: 10px;
  font-style: normal;
  letter-spacing: 0.5px;
  color: var(--b-400);
}

.tools-section h2 {
  margin: 0 0 0.35rem;
  font-family: var(--b-mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--b-500);
}

.tools-note {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--b-400);
}

.tools-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
  gap: 1rem;
  margin: 1rem 0 0;
  padding: 0;
  list-style: none;
}

.tools-card {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  height: 100%;
  padding: 1.25rem;
  background: var(--b-bg);
  border: 1px solid var(--b-200);
  border-radius: var(--b-r-card);
  text-decoration: none;
  color: inherit;
  transition: border-color 0.2s var(--b-ease), transform 0.2s var(--b-ease);
}

.tools-card:hover {
  border-color: var(--b-green);
  transform: translateY(-2px);
}

.tools-card:focus-visible {
  outline: 2px solid var(--b-green);
  outline-offset: 2px;
}

.tools-mark {
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  background: var(--b-50);
  border: 1px solid var(--b-200);
  font-family: var(--b-display);
  font-size: 11px;
  letter-spacing: 0.5px;
  color: var(--b-green-text);
}

.tools-card-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
}

.tools-card-head strong {
  font-size: 1.02rem;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.tools-platform {
  padding: 0.12rem 0.45rem;
  background: var(--b-50);
  border: 1px solid var(--b-200);
  font-family: var(--b-mono);
  font-size: 9px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: var(--b-500);
}

.tools-blurb {
  font-size: 0.9rem;
  line-height: 1.5;
  color: var(--b-700);
}

.tools-desc {
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--b-500);
}

.tools-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.15rem;
}

.tools-tags span {
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.4px;
  color: var(--b-400);
}

.tools-tags span + span::before {
  content: '• ';
  color: var(--b-300);
}

.tools-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: auto;
  padding-top: 0.75rem;
  border-top: 1px solid var(--b-200);
}

.tools-host {
  font-family: var(--b-mono);
  font-size: 10px;
  letter-spacing: 0.4px;
  color: var(--b-400);
  overflow-wrap: anywhere;
}

.tools-go {
  color: var(--b-green-text);
  transition: transform 0.2s var(--b-ease);
}

.tools-card:hover .tools-go {
  transform: translateX(3px);
}

.tools-footer {
  padding-top: 1.25rem;
  border-top: 1px solid var(--b-200);
}

.tools-footer p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--b-500);
}

.tools-footer :deep(a) {
  color: var(--b-green-text);
  text-decoration: underline;
  text-underline-offset: 2px;
}

@media (max-width: 760px) {
  .tools-hero {
    grid-template-columns: minmax(0, 1fr);
    align-items: start;
  }

  .tools-hero blockquote {
    text-align: left;
  }

  .tools-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
