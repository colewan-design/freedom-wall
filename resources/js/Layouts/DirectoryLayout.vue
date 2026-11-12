<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';

// The directory shell: a sticky bar with the search in it, one wide column of
// cards, a narrow rail beside it, then the CTA band and the footer. Pages fill
// the rail through the #rail slot — the layout only owns the frame.
//
// The search box lives up here but filters the page's own list, so the term is
// a v-model rather than a provide: slot content is rendered by the page, which
// sits above this component and so cannot inject from it.

const props = defineProps({
  search: { type: String, default: '' },
  // What the bar says it searches, since each page searches its own list.
  searchPlaceholder: { type: String, default: 'Search posts' },
});

const emit = defineEmits(['update:search']);

const searchTerm = computed({
  get: () => props.search,
  set: (value) => emit('update:search', value),
});

const page = usePage();

const authUser = computed(() => page.props.auth?.user ?? null);
const isStudentAuthed = computed(() => authUser.value?.role === 'student');
const isWallPage = computed(() => page.component === 'Wall');

const NAV = [
  { href: '/wall', label: 'News Feed' },
  { href: '/threads', label: 'Threads' },
  { href: '/chat', label: 'Chat' },
  { href: '/jobs', label: 'Jobs' },
  { href: '/id-check', label: 'ID Check' },
  { href: '/tools', label: 'Tools' },
];

const FOOTER_COLUMNS = [
  {
    title: 'The wall',
    links: [
      { href: '/wall', label: 'News Feed' },
      { href: '/threads', label: 'Threads' },
      { href: '/chat', label: 'Chat room' },
    ],
  },
  {
    title: 'Tools',
    links: [
      { href: '/jobs', label: 'JobRadar' },
      { href: '/id-check', label: 'BSUkol ID Check' },
      { href: '/tools', label: 'All tools' },
      { href: '/support', label: 'Support the wall' },
    ],
  },
  {
    title: 'Account',
    links: [
      { href: '/login', label: 'Log in' },
      { href: '/register', label: 'Create an account' },
    ],
  },
];

const isActive = (href) =>
  page.url === href || page.url.startsWith(`${href}?`) || page.url.startsWith(`${href}/`);

const searchInput = ref(null);

// Read before first paint rather than in onMounted: this shell is not an
// Inertia persistent layout, so it remounts on every navigation and a deferred
// read would flash the light palette at anyone running dark.
const theme = ref(readSavedTheme());

function readSavedTheme() {
  try {
    return localStorage.getItem('wall-theme') === 'light' ? 'light' : 'dark';
  } catch {
    return 'dark';
  }
}

onMounted(() => window.addEventListener('keydown', focusSearchWithSlash));

onBeforeUnmount(() => window.removeEventListener('keydown', focusSearchWithSlash));

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('wall-theme', theme.value);
}

// Overlays teleport to <body>, outside this subtree, so the palette has to ride
// on <html> rather than on the shell element.
watchEffect(() => {
  if (typeof document === 'undefined') return;
  document.documentElement.setAttribute('data-theme', theme.value);
});

function focusSearchWithSlash(event) {
  if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
  if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) return;
  event.preventDefault();
  searchInput.value?.focus();
}

// On the wall the composer is a click away, so open it in place. Anywhere else
// the hash is what Wall.vue reads on mount to open it after the navigation.
function openComposer() {
  document.querySelector('#composer')?.click();
}

const year = new Date().getFullYear();

// Unique visitors, counted once per person per day. Null when the count could
// not be read, in which case the line is simply left out.
const visitors = computed(() => page.props.visitorStats ?? null);
const formatCount = (value) => Number(value).toLocaleString('en-PH');
</script>

<template>
  <div class="dir">
    <header class="dir-top">
      <div class="dir-top-inner">
        <Link href="/wall" class="dir-brand">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="dir-brand-mark" />
          <span>BSU<b>Freedom Wall</b></span>
        </Link>

        <nav class="dir-nav">
          <Link v-for="item in NAV" :key="item.href" :href="item.href" :class="{ active: isActive(item.href) }">
            {{ item.label }}
          </Link>
        </nav>

        <div class="dir-top-right">
          <label class="dir-search">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8" />
              <path d="m20 20-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input
              ref="searchInput"
              v-model="searchTerm"
              type="text"
              :placeholder="searchPlaceholder"
              :aria-label="searchPlaceholder"
            />
            <kbd>/</kbd>
          </label>

          <button
            type="button"
            class="dir-icon-btn"
            :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
            @click="toggleTheme"
          >
            <svg v-if="theme === 'dark'" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6" />
              <path
                d="M12 2.5v2M12 19.5v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2.5 12h2M19.5 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linecap="round"
              />
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"
                stroke="currentColor"
                stroke-width="1.6"
                stroke-linejoin="round"
              />
            </svg>
          </button>

          <Link v-if="isStudentAuthed" href="/feed" class="dir-login">{{ authUser.name }}</Link>
          <Link v-else href="/login" class="dir-login">Log in</Link>
        </div>
      </div>
    </header>

    <div class="dir-body">
      <main class="dir-main">
        <slot />
      </main>

      <aside v-if="$slots.rail" class="dir-rail">
        <slot name="rail" />
      </aside>
    </div>

    <section class="dir-cta-wrap">
      <div class="dir-cta">
        <span class="dir-cta-dots" aria-hidden="true"></span>
        <span class="dir-cta-badge">Say it anonymously</span>
        <h2>Something you can only say here?</h2>
        <p>
          Post without a name, without an account. A human reads every submission
          before it goes up, so the wall stays worth reading.
        </p>
        <button v-if="isWallPage" type="button" class="dir-cta-btn" @click="openComposer">
          Write a post
          <span aria-hidden="true">&rarr;</span>
        </button>
        <Link v-else href="/wall#composer" class="dir-cta-btn">
          Write a post
          <span aria-hidden="true">&rarr;</span>
        </Link>
      </div>
    </section>

    <footer class="dir-foot">
      <div class="dir-foot-inner">
        <div class="dir-foot-brand">
          <img src="/images/branding/bsufw-mark-64.png" alt="" class="dir-brand-mark" />
          <p>Home for anonymous posts from the BSU community. Written by students, reviewed by humans, read by everyone.</p>
        </div>

        <div v-for="column in FOOTER_COLUMNS" :key="column.title" class="dir-foot-col">
          <h3>{{ column.title }}</h3>
          <Link v-for="link in column.links" :key="link.href" :href="link.href">{{ link.label }}</Link>
        </div>
      </div>

      <div class="dir-foot-bar">
        <span>&copy; {{ year }} BSU Freedom Wall. Student-run.</span>
        <span v-if="visitors" class="dir-foot-visitors" title="Unique visitors, each counted once a day">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
          </svg>
          {{ formatCount(visitors.total) }} visitors &middot; {{ formatCount(visitors.today) }} today
        </span>
        <span>Anonymous &middot; Reviewed before posting</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.dir {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--d-canvas);
  color: var(--d-ink);
  font-family: var(--b-sans);
}

/* --- Top bar -------------------------------------------------------------- */

.dir-top {
  position: sticky;
  top: 0;
  z-index: 30;
  background: var(--d-canvas);
  border-bottom: 1px solid var(--d-line);
}

.dir-top-inner {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 1rem;
  max-width: 1180px;
  margin: 0 auto;
  padding: 0.7rem 1.25rem;
}

.dir-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.95rem;
  color: var(--d-ink);
  text-decoration: none;
  justify-self: start;
  white-space: nowrap;
}

.dir-brand span {
  font-weight: 500;
  letter-spacing: -0.01em;
}

.dir-brand b {
  font-weight: 800;
  margin-left: 0.2rem;
}

.dir-brand-mark {
  width: 1.85rem;
  height: 1.85rem;
  border-radius: var(--d-r-sm);
  object-fit: cover;
  flex-shrink: 0;
}

.dir-nav {
  display: flex;
  align-items: center;
  gap: 1.35rem;
}

.dir-nav :deep(a) {
  font-size: 0.86rem;
  font-weight: 600;
  color: var(--d-muted);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.15s ease;
}

.dir-nav :deep(a:hover) {
  color: var(--d-ink);
}

.dir-nav :deep(a.active) {
  color: var(--d-ink);
}

.dir-top-right {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  justify-self: end;
}

.dir-search {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  width: 15rem;
  padding: 0.42rem 0.5rem 0.42rem 0.7rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  color: var(--d-faint);
  transition: border-color 0.15s ease;
}

.dir-search:focus-within {
  border-color: var(--d-green);
}

.dir-search input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: none;
  font: inherit;
  font-size: 0.83rem;
  color: var(--d-ink);
}

.dir-search input::placeholder {
  color: var(--d-faint);
}

.dir-search kbd {
  font-family: var(--b-mono);
  font-size: 0.66rem;
  line-height: 1;
  padding: 0.25rem 0.4rem;
  border-radius: var(--d-r-sm);
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  color: var(--d-faint);
}

.dir-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: var(--d-r-pill);
  border: 1px solid var(--d-line);
  background: var(--d-card);
  color: var(--d-muted);
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.dir-icon-btn:hover {
  color: var(--d-ink);
  border-color: var(--d-line-strong);
}

.dir-login {
  padding: 0.48rem 0.95rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.83rem;
  font-weight: 650;
  text-decoration: none;
  white-space: nowrap;
  transition: background 0.15s ease;
}

.dir-login:hover {
  background: var(--d-green-hover);
}

/* --- Body ----------------------------------------------------------------- */

.dir-body {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  align-items: start;
  gap: 1.5rem;
  width: 100%;
  max-width: 1180px;
  margin: 0 auto;
  padding: 1.75rem 1.25rem 2.5rem;
}

.dir-main {
  min-width: 0;
}

/* The rail outgrows the viewport once a page stacks a few cards in it, and a
   sticky column taller than the screen strands its bottom cards off-screen.
   Cap it to the space under the header and let it scroll on its own instead.
   The negative margin pulls the gutter back so the cards keep their width. */
.dir-rail {
  position: sticky;
  top: 4.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  max-height: calc(100vh - 5.5rem);
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-right: 0.4rem;
  margin-right: -0.4rem;
  scrollbar-width: thin;
  scrollbar-color: var(--d-line-strong) transparent;
}

.dir-rail::-webkit-scrollbar {
  width: 6px;
}

.dir-rail::-webkit-scrollbar-track {
  background: transparent;
}

.dir-rail::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: var(--d-line-strong);
}

/* --- Call to action ------------------------------------------------------- */

.dir-cta-wrap {
  max-width: 1180px;
  width: 100%;
  margin: 0 auto;
  padding: 1rem 1.25rem 3.5rem;
}

.dir-cta {
  position: relative;
  overflow: hidden;
  padding: 2.4rem 2.5rem;
  border-radius: var(--d-r-xl);
  background: var(--d-green);
  color: var(--d-green-ink);
}

.dir-cta-dots {
  position: absolute;
  inset: 0 0 0 auto;
  width: 22rem;
  background-image: radial-gradient(currentColor 1.5px, transparent 1.6px);
  background-size: 14px 14px;
  opacity: 0.22;
  mask-image: linear-gradient(90deg, transparent, #000 60%);
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 60%);
  pointer-events: none;
}

.dir-cta-badge {
  position: relative;
  display: inline-block;
  padding: 0.3rem 0.7rem;
  border-radius: var(--d-r-pill);
  background: rgba(255, 255, 255, 0.18);
  font-size: 0.72rem;
  font-weight: 650;
  letter-spacing: 0.01em;
}

.dir-cta h2 {
  position: relative;
  margin: 0.85rem 0 0.5rem;
  font-size: 1.7rem;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.dir-cta p {
  position: relative;
  margin: 0 0 1.35rem;
  max-width: 34rem;
  font-size: 0.92rem;
  line-height: 1.6;
  opacity: 0.92;
}

.dir-cta-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.6rem 1.1rem;
  border: 0;
  border-radius: var(--d-r-md);
  background: var(--d-card);
  color: var(--d-green-text);
  font: inherit;
  font-size: 0.87rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.15s var(--b-ease);
}

.dir-cta-btn:hover {
  transform: translateY(-1px);
}

/* --- Footer --------------------------------------------------------------- */

.dir-foot {
  background: var(--d-footer);
  color: var(--d-footer-ink);
  /* In dark mode the footer and the canvas are a shade apart, so the seam
     needs a line to read as a separate band. */
  border-top: 1px solid var(--d-line);
}

.dir-foot-inner {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(3, minmax(0, 1fr));
  gap: 2rem;
  max-width: 1180px;
  margin: 0 auto;
  padding: 3rem 1.25rem 2.25rem;
}

.dir-foot-brand p {
  margin: 0.9rem 0 0;
  max-width: 22rem;
  font-size: 0.84rem;
  line-height: 1.65;
  color: var(--d-footer-muted);
}

.dir-foot-col h3 {
  margin: 0 0 0.9rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--d-footer-muted);
}

.dir-foot-col :deep(a) {
  display: block;
  margin-bottom: 0.55rem;
  font-size: 0.85rem;
  color: var(--d-footer-ink);
  text-decoration: none;
  transition: color 0.15s ease;
}

.dir-foot-col :deep(a:hover) {
  color: var(--d-green-text);
}

.dir-foot-visitors {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-variant-numeric: tabular-nums;
}

.dir-foot-bar {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  max-width: 1180px;
  margin: 0 auto;
  padding: 1.1rem 1.25rem 1.6rem;
  border-top: 1px solid var(--d-footer-line);
  font-size: 0.78rem;
  color: var(--d-footer-muted);
}

/* --- Responsive ----------------------------------------------------------- */

@media (max-width: 1040px) {
  .dir-body {
    grid-template-columns: minmax(0, 1fr);
  }

  /* Single column: the rail flows under the feed, so it scrolls with the page
     rather than inside its own box. */
  .dir-rail {
    position: static;
    max-height: none;
    overflow-y: visible;
    padding-right: 0;
    margin-right: 0;
  }
}

@media (max-width: 900px) {
  /* The search track is minmax(0, …) so the nav's and the search box's
     min-content widths shrink instead of pushing the bar past the viewport.
     The brand track stays `auto`: at minmax(0, …) it collapses under the mark,
     and since the mark can't shrink it ends up sitting under the search. */
  .dir-top-inner {
    grid-template-columns: auto minmax(0, 1fr);
    row-gap: 0.65rem;
  }

  .dir-nav {
    grid-row: 2;
    grid-column: 1 / -1;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: none;
  }

  /* Stretched rather than end-aligned: a stretched item is exactly its track's
     width, so a crowded bar overflows to the right, past the viewport edge,
     instead of spilling leftwards over the logo. */
  .dir-top-right {
    justify-self: stretch;
    justify-content: flex-end;
    min-width: 0;
  }

  .dir-nav::-webkit-scrollbar {
    display: none;
  }

  .dir-search {
    flex: 1;
    width: auto;
    min-width: 0;
    max-width: 11rem;
  }

  /* A long display name truncates instead of shoving the row leftwards. */
  .dir-login {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .dir-search kbd {
    display: none;
  }

  .dir-foot-inner {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 600px) {
  /* The mark alone carries the brand once the search needs the room. */
  .dir-brand span {
    display: none;
  }

  .dir-search {
    max-width: none;
  }
}

@media (max-width: 640px) {
  .dir-cta {
    padding: 1.8rem 1.4rem;
  }

  .dir-cta h2 {
    font-size: 1.35rem;
  }

  .dir-foot-inner {
    grid-template-columns: minmax(0, 1fr);
    gap: 1.75rem;
  }
}
</style>
