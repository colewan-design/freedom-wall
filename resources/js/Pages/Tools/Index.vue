<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';
import { APPS, EXTERNAL_APPS, SITE_APPS } from '../../lib/apps';

// Rendered here rather than through defineOptions because the page fills the
// shell's #rail slot, which Inertia's persistent-layout option cannot reach.

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

const search = ref('');

function matches(app, term) {
  return [app.name, app.blurb, app.description, app.platform, ...(app.tags ?? [])]
    .join(' ')
    .toLowerCase()
    .includes(term);
}

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return APPS;
  return APPS.filter((app) => matches(app, term));
});

// The list is static, so number the sections here rather than hard-coding a
// heading that goes wrong the day one of the groups is empty.
const GROUPS = computed(() =>
  [
    {
      id: 'here',
      title: 'On this site',
      note: 'Open in place — no new tab, no sign-up.',
      apps: decorate(filtered.value.filter((app) => app.internal)),
    },
    {
      id: 'elsewhere',
      title: 'Elsewhere on the web',
      note: 'These open in a new tab.',
      apps: decorate(filtered.value.filter((app) => !app.internal)),
    },
  ].filter((group) => group.apps.length),
);
</script>

<template>
  <Head title="Tools" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search tools">
    <div class="tools">
      <header class="hero">
        <h1>Side projects from the people who run the wall</h1>
        <p class="hero-lede">
          Everything we have shipped, in one place. All free, all open to
          anyone — no student account needed.
        </p>
        <div class="hero-actions">
          <a href="#tools-here" class="btn btn-green">Browse the apps <span aria-hidden="true">&rarr;</span></a>
          <Link href="/threads" class="btn btn-plain">Suggest one</Link>
          <p class="hero-proof">
            <span class="proof-stack" aria-hidden="true">
              <i v-for="app in APPS.slice(0, 4)" :key="app.id">{{ app.mark }}</i>
            </span>
            <span>{{ APPS.length }} shipped</span>
          </p>
        </div>
      </header>

      <section
        v-for="group in GROUPS"
        :id="`tools-${group.id}`"
        :key="group.id"
        class="tools-section"
        :aria-labelledby="`tools-head-${group.id}`"
      >
        <div class="section-head">
          <div>
            <h2 :id="`tools-head-${group.id}`">{{ group.title }}</h2>
            <p v-if="group.note">{{ group.note }}</p>
          </div>
          <span class="count-pill">{{ group.apps.length }}</span>
        </div>

        <ul class="tools-grid">
          <li v-for="app in group.apps" :key="app.id">
            <component :is="app.component" v-bind="app.attrs" class="tools-card">
              <span class="tools-media" :class="{ 'is-fallback': !app.cover }" aria-hidden="true">
                <img v-if="app.cover" :src="app.cover" alt="" loading="lazy" decoding="async" class="tools-cover" />
                <span
                  v-else-if="app.wordmark || app.logo"
                  class="tools-art"
                  :class="{ 'has-dark-art': app.wordmarkDark }"
                >
                  <img
                    :src="app.wordmark ?? app.logo"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="tools-fallback-art on-light"
                    :class="{ 'is-wordmark': app.wordmark }"
                  />
                  <img
                    v-if="app.wordmarkDark"
                    :src="app.wordmarkDark"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="tools-fallback-art on-dark is-wordmark"
                  />
                </span>
                <span v-else class="tools-mark">{{ app.mark }}</span>
              </span>

              <span class="tools-body">
                <span class="tools-card-head">
                  <img v-if="app.logo" :src="app.logo" alt="" loading="lazy" decoding="async" class="tools-logo" />
                  <span v-else class="tile tile-sm">{{ app.mark }}</span>
                  <span class="tools-name">
                    <strong>{{ app.name }}</strong>
                    <small>{{ app.platform }}</small>
                  </span>
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
              </span>
            </component>
          </li>
        </ul>
      </section>

      <p v-if="!GROUPS.length" class="empty">Nothing matches “{{ search }}”.</p>
    </div>

    <template #rail>
      <p class="rail-stat">
        <span>Tools shipped</span>
        <strong>{{ APPS.length }}</strong>
        <i>· {{ SITE_APPS.length }} on this site</i>
      </p>

      <section class="rail-card">
        <div class="rail-head">
          <h2>On this site</h2>
        </div>
        <ul class="rail-apps">
          <li v-for="app in SITE_APPS" :key="app.id">
            <Link :href="app.url">
              <img v-if="app.logo" :src="app.logo" alt="" loading="lazy" decoding="async" />
              <span v-else class="tile tile-sm" aria-hidden="true">{{ app.mark }}</span>
              <span class="rail-app-text">
                <b>{{ app.name }}</b>
                <small>{{ app.blurb }}</small>
              </span>
            </Link>
          </li>
        </ul>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Elsewhere</h2>
        </div>
        <ul class="rail-apps">
          <li v-for="app in EXTERNAL_APPS" :key="app.id">
            <a :href="app.url" target="_blank" rel="noopener noreferrer">
              <img v-if="app.logo" :src="app.logo" alt="" loading="lazy" decoding="async" />
              <span v-else class="tile tile-sm" aria-hidden="true">{{ app.mark }}</span>
              <span class="rail-app-text">
                <b>{{ app.name }}</b>
                <small>{{ app.host }}</small>
              </span>
            </a>
          </li>
        </ul>
      </section>

      <section class="rail-card rail-promo">
        <h2>Got an idea?</h2>
        <p>
          Something broken, or a tool you wish existed? Say so on the forum —
          we read everything there.
        </p>
        <Link href="/threads" class="btn btn-green btn-block">
          Open Threads <span aria-hidden="true">&rarr;</span>
        </Link>
      </section>
    </template>
  </DirectoryLayout>
</template>

<style scoped>
/* The directory skin, same tokens as the wall: cards on a soft canvas, one
   accent green, hairline borders. */

.tools {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: tools / inline-size;
}

/* --- Shared furniture ----------------------------------------------------- */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
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

.btn-green {
  background: var(--d-green);
  color: var(--d-green-ink);
}

.btn-green:hover {
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
  font-size: 0.68rem;
}

.empty {
  margin: 0;
  padding: 1.4rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  font-size: 0.88rem;
  color: var(--d-muted);
  text-align: center;
}

/* --- Hero ----------------------------------------------------------------- */

.hero {
  padding: 1.5rem 0 0.5rem;
}

.hero h1 {
  margin: 0;
  max-width: 20ch;
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

.hero-actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
  margin-top: 1.35rem;
}

.hero-proof {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  margin: 0 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--d-muted);
}

.proof-stack {
  display: inline-flex;
}

.proof-stack i {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.7rem;
  height: 1.7rem;
  margin-left: -0.5rem;
  border-radius: 50%;
  border: 2px solid var(--d-canvas);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.6rem;
  font-style: normal;
  font-weight: 800;
}

.proof-stack i:first-child {
  margin-left: 0;
}

/* --- Sections ------------------------------------------------------------- */

.section-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin: 1.2rem 0 0.9rem;
}

.section-head h2 {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--d-ink);
}

.section-head p {
  margin: 0.3rem 0 0;
  font-size: 0.86rem;
  color: var(--d-muted);
}

.count-pill {
  padding: 0.2rem 0.6rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  color: var(--d-green-text);
  font-size: 0.76rem;
  font-weight: 700;
}

/* --- Cards ---------------------------------------------------------------- */

.tools-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.tools-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
  text-decoration: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s var(--b-ease);
}

.tools-card:hover {
  border-color: var(--d-line-strong);
  box-shadow: var(--d-shadow-lift);
  transform: translateY(-2px);
}

.tools-media {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--d-soft);
  border-bottom: 1px solid var(--d-line);
}

.tools-media.is-fallback {
  background: var(--d-green-tint);
}

.tools-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tools-art {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  padding: 1.5rem;
}

.tools-fallback-art {
  max-width: 100%;
  max-height: 3.4rem;
  object-fit: contain;
}

.tools-fallback-art.is-wordmark {
  max-height: 2.4rem;
}

/* A brand whose wordmark is dark ink would sink into the dark panel, so the
 * ones that ship a light variant swap on theme. Both images are in the markup
 * and the theme decides, which keeps the swap free of any JavaScript the
 * layout would have to hand down. */
.tools-art .on-dark {
  display: none;
}

:global(html[data-theme='dark']) .tools-art.has-dark-art .on-light {
  display: none;
}

:global(html[data-theme='dark']) .tools-art.has-dark-art .on-dark {
  display: block;
}

.tools-mark {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--d-green-text);
}

.tools-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  padding: 0.85rem 0.95rem 0.8rem;
}

.tools-card-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tools-logo {
  width: 1.8rem;
  height: 1.8rem;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  object-fit: cover;
  background: var(--d-soft);
}

.tools-name {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.tools-name strong {
  font-size: 0.95rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.tools-name small {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.tools-blurb {
  margin-top: 0.6rem;
  font-size: 0.86rem;
  font-weight: 600;
  color: var(--d-ink-2);
}

.tools-desc {
  margin-top: 0.3rem;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.tools-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  margin-top: 0.7rem;
}

.tools-tags span {
  padding: 0.2rem 0.5rem;
  border-radius: var(--d-r-pill);
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--d-muted);
}

.tools-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.7rem;
  font-size: 0.76rem;
  color: var(--d-muted);
}

.tools-host {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tools-go {
  color: var(--d-green-text);
  font-weight: 700;
  transition: transform 0.15s var(--b-ease);
}

.tools-card:hover .tools-go {
  transform: translateX(3px);
}

/* --- Rail ----------------------------------------------------------------- */

.rail-stat {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
  margin: 0 0 0.1rem;
  padding: 0 0.2rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.rail-stat strong {
  font-size: 0.9rem;
  letter-spacing: -0.01em;
  color: var(--d-ink);
}

.rail-stat i {
  font-style: normal;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: none;
}

.rail-card {
  padding: 0.9rem 0.95rem 1rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
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

.rail-apps {
  margin: 0;
  padding: 0;
  list-style: none;
}

.rail-apps :deep(a) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem;
  border-radius: var(--d-r-md);
  text-decoration: none;
  transition: background 0.15s ease;
}

.rail-apps :deep(a:hover) {
  background: var(--d-soft);
}

.rail-apps img {
  width: 1.65rem;
  height: 1.65rem;
  flex-shrink: 0;
  border-radius: var(--d-r-sm);
  object-fit: cover;
  background: var(--d-soft);
}

.rail-app-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.rail-app-text b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8rem;
  font-weight: 650;
  color: var(--d-ink);
}

.rail-app-text small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.68rem;
  color: var(--d-muted);
}

.rail-promo p {
  margin: 0.5rem 0 0;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--d-muted);
}

/* --- Responsive ----------------------------------------------------------- */

@container tools (max-width: 700px) {
  .tools-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .hero h1 {
    font-size: 2rem;
  }
}

@container tools (max-width: 540px) {
  .hero h1 {
    font-size: 1.7rem;
  }

  .section-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.4rem;
  }
}
</style>
