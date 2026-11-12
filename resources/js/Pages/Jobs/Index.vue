<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';
import JobCard from '../../Components/JobCard.vue';
import TurnstileWidget from '../../Components/TurnstileWidget.vue';
import { POSTED_OPTIONS, SECTOR_LABELS, TYPE_LABELS } from '../../lib/jobs';

// JobRadar's board. Filtering happens on the server — the board can be far
// longer than one page — so every control writes the query string and asks
// Inertia for the page again, keeping scroll and the half-typed search.

const props = defineProps({
  listings: { type: Object, required: true },
  filters: { type: Object, required: true },
  savedIds: { type: Array, default: () => [] },
  canTrack: { type: Boolean, default: false },
  options: { type: Object, required: true },
  insights: { type: Object, required: true },
});

const page = usePage();

const search = ref(props.filters.q ?? '');
const location = ref(props.filters.location ?? '');
const type = ref(props.filters.type ?? '');
const sector = ref(props.filters.sector ?? '');
const posted = ref(props.filters.posted ?? '');
const remote = ref(Boolean(props.filters.remote));
const employer = ref(props.filters.employer ?? '');

const saved = computed(() => new Set(props.savedIds));

const hasFilters = computed(
  () => Boolean(search.value || location.value || type.value || sector.value || posted.value || remote.value || employer.value),
);

// Agencies first: most students who filter by employer are after a
// government post. The server already sorts each group by openings.
const employerGroups = computed(() => {
  const all = props.options.employers ?? [];

  return [
    { label: 'Government', items: all.filter((item) => item.government) },
    { label: 'Private and others', items: all.filter((item) => !item.government) },
  ].filter((group) => group.items.length);
});

// A filter from an old link can name an employer with nothing open now; keep
// it selectable so the select does not silently show "Any".
const employerMissing = computed(
  () => employer.value && !(props.options.employers ?? []).some((item) => item.name === employer.value),
);

function query() {
  return Object.fromEntries(
    Object.entries({
      q: search.value.trim(),
      location: location.value.trim(),
      type: type.value,
      sector: sector.value,
      posted: posted.value,
      employer: employer.value,
      remote: remote.value ? 1 : '',
    }).filter(([, value]) => value !== '' && value !== null),
  );
}

function apply() {
  router.get('/jobs', query(), { preserveState: true, preserveScroll: true, replace: true });
}

let typing = null;

function applySoon() {
  clearTimeout(typing);
  typing = setTimeout(apply, 400);
}

onBeforeUnmount(() => clearTimeout(typing));

watch(search, applySoon);
watch([type, sector, posted, remote, employer], apply);

function clearFilters() {
  search.value = '';
  location.value = '';
  type.value = '';
  sector.value = '';
  posted.value = '';
  remote.value = false;
  employer.value = '';
}

function filterLocation(label) {
  location.value = label;
  apply();
}

// The watcher on `employer` does the request.
function filterEmployer(name) {
  employer.value = name;
}

function toggleSave(listing) {
  const options = { preserveScroll: true, preserveState: true, only: ['savedIds'] };

  if (saved.value.has(listing.id)) {
    router.delete(`/jobs/${listing.id}/save`, options);
  } else {
    router.post(`/jobs/${listing.id}/save`, {}, options);
  }
}

// --- Share a job -------------------------------------------------------

const sharing = ref(false);
const isStudent = computed(() => page.props.auth?.user?.role === 'student');

const shareForm = useForm({
  apply_url: '',
  title: '',
  employer: '',
  location: '',
  is_remote: false,
  employment_type: '',
  sector: '',
  salary_text: '',
  deadline: '',
  description: '',
  captchaToken: null,
});

function openShare() {
  sharing.value = true;
  document.body.style.overflow = 'hidden';
}

function closeShare() {
  sharing.value = false;
  document.body.style.overflow = '';
}

function submitShare() {
  shareForm
    .transform((data) => ({
      ...data,
      employment_type: data.employment_type || null,
      sector: data.sector || null,
      deadline: data.deadline || null,
    }))
    .post('/jobs', {
      preserveScroll: true,
      onSuccess: () => {
        shareForm.reset();
        closeShare();
      },
    });
}

onBeforeUnmount(() => {
  document.body.style.overflow = '';
});

// --- Flash -------------------------------------------------------------

const toast = ref('');
let toastTimer = null;

watch(
  () => page.props.flash?.success,
  (message) => {
    if (!message) return;
    toast.value = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.value = ''), 5000);
  },
  { immediate: true },
);

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
  <Head title="Jobs" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search jobs">
    <div class="jobs">
      <header class="hero">
        <p class="hero-badge">JobRadar</p>
        <h1>Find openings before you miss them</h1>
        <p class="hero-lede">
          Vacancies from employer feeds, job boards and links shared by students, in one list.
          Every job links back to where it was first posted.
        </p>
        <div class="hero-actions">
          <Link v-if="canTrack" href="/jobs/mine" class="btn btn-green">
            My jobs &amp; alerts <span aria-hidden="true">&rarr;</span>
          </Link>
          <Link v-else href="/login" class="btn btn-green">
            Log in for job alerts <span aria-hidden="true">&rarr;</span>
          </Link>
          <button type="button" class="btn btn-plain" @click="openShare">Share a job</button>
        </div>
      </header>

      <section class="filters" aria-label="Filter jobs">
        <label class="field field-grow">
          <span>Location</span>
          <input
            v-model="location"
            type="text"
            placeholder="City, municipality or province"
            @keydown.enter.prevent="apply"
            @blur="apply"
          />
        </label>

        <label class="field">
          <span>Type</span>
          <select v-model="type">
            <option value="">Any type</option>
            <option v-for="value in options.types" :key="value" :value="value">{{ TYPE_LABELS[value] }}</option>
          </select>
        </label>

        <label class="field">
          <span>Sector</span>
          <select v-model="sector">
            <option value="">Any sector</option>
            <option v-for="value in options.sectors" :key="value" :value="value">{{ SECTOR_LABELS[value] }}</option>
          </select>
        </label>

        <label class="field field-employer">
          <span>Hiring agency</span>
          <select v-model="employer">
            <option value="">Any employer</option>
            <option v-if="employerMissing" :value="employer">{{ employer }} (none open)</option>
            <optgroup v-for="group in employerGroups" :key="group.label" :label="group.label">
              <option v-for="item in group.items" :key="item.name" :value="item.name">
                {{ item.name }} ({{ item.total }})
              </option>
            </optgroup>
          </select>
        </label>

        <label class="field">
          <span>Posted</span>
          <select v-model="posted">
            <option value="">Any time</option>
            <option v-for="option in POSTED_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>

        <label class="check">
          <input v-model="remote" type="checkbox" />
          <span>Include remote</span>
        </label>
      </section>

      <div class="section-head">
        <div>
          <h2>{{ hasFilters ? 'Matching jobs' : 'Latest jobs' }}</h2>
          <p>Newest first, by when we found them.</p>
        </div>
        <span class="count-pill">{{ listings.total }}</span>
      </div>

      <ul v-if="listings.data.length" class="job-list">
        <li v-for="(listing, index) in listings.data" :key="listing.id" class="rise" :style="{ '--i': Math.min(index, 4) }">
          <JobCard
            :listing="listing"
            :can-save="canTrack"
            :saved="saved.has(listing.id)"
            @toggle-save="toggleSave"
          />
        </li>
      </ul>

      <div v-else class="empty">
        <template v-if="hasFilters">
          No open jobs match those filters.
          <button type="button" class="link-btn" @click="clearFilters(); apply()">Clear filters</button>
        </template>
        <template v-else>
          No jobs on the board yet. Seen one somewhere?
          <button type="button" class="link-btn" @click="openShare">Share it here</button>
        </template>
      </div>

      <nav v-if="listings.lastPage > 1" class="pager" aria-label="Pages">
        <Link v-if="listings.prevUrl" :href="listings.prevUrl" preserve-scroll class="btn btn-plain btn-sm">&larr; Newer</Link>
        <span>Page {{ listings.page }} of {{ listings.lastPage }}</span>
        <Link v-if="listings.nextUrl" :href="listings.nextUrl" preserve-scroll class="btn btn-plain btn-sm">Older &rarr;</Link>
      </nav>
    </div>

    <template #rail>
      <p class="rail-stat">
        <span>Open jobs</span>
        <strong>{{ insights.open }}</strong>
        <i>· {{ insights.thisWeek }} this week</i>
      </p>

      <section class="rail-card rail-promo">
        <h2>Get told, not reminded</h2>
        <p>
          Save a search as a monitor and JobRadar keeps checking. New matches show up on your
          page and in a daily or weekly email.
        </p>
        <Link v-if="canTrack" href="/jobs/mine#monitors" class="btn btn-green btn-block">
          Set up a monitor <span aria-hidden="true">&rarr;</span>
        </Link>
        <Link v-else-if="!isStudent" href="/register" class="btn btn-green btn-block">
          Create a free account <span aria-hidden="true">&rarr;</span>
        </Link>
        <Link v-else href="/verify-email" class="btn btn-green btn-block">Verify your email first</Link>
      </section>

      <section v-if="insights.topLocations.length" class="rail-card">
        <div class="rail-head">
          <h2>Hiring around here</h2>
        </div>
        <ul class="rail-rank">
          <li v-for="row in insights.topLocations" :key="row.label">
            <button type="button" @click="filterLocation(row.label)">{{ row.label }}</button>
            <b>{{ row.total }}</b>
          </li>
        </ul>
      </section>

      <section v-if="insights.topEmployers.length" class="rail-card">
        <div class="rail-head">
          <h2>Most openings</h2>
        </div>
        <ul class="rail-rank">
          <li v-for="row in insights.topEmployers" :key="row.label">
            <button type="button" @click="filterEmployer(row.label)">{{ row.label }}</button>
            <b>{{ row.total }}</b>
          </li>
        </ul>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>On the board</h2>
        </div>
        <dl class="rail-stats">
          <div><dt>Remote</dt><dd>{{ insights.remote }}</dd></div>
          <div><dt>Government</dt><dd>{{ insights.government }}</dd></div>
          <div><dt>Sources watched</dt><dd>{{ insights.sources }}</dd></div>
        </dl>
        <p class="rail-note">Counts cover the jobs on this board, not the whole job market.</p>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Staying safe</h2>
        </div>
        <ul class="rail-tips">
          <li>Real employers do not charge you to apply, train or get hired.</li>
          <li>Apply through the employer's own site or office, not a stranger's chat.</li>
          <li>A warning on a listing means look closer. It does not mean the job is fake.</li>
        </ul>
      </section>
    </template>
  </DirectoryLayout>

  <Teleport to="body">
    <Transition name="toast">
      <p v-if="toast" class="jobs-toast" role="status">{{ toast }}</p>
    </Transition>

    <div v-if="sharing" class="share-scrim" @click.self="closeShare">
      <form class="share" aria-labelledby="share-title" @submit.prevent="submitShare" @keydown.esc="closeShare">
        <header class="share-head">
          <div>
            <h2 id="share-title">Share a job</h2>
            <p>Found an opening on a page, a group or a poster? Link it here. A moderator checks it before it goes up.</p>
          </div>
          <button type="button" class="share-close" aria-label="Close" @click="closeShare">&times;</button>
        </header>

        <label class="field">
          <span>Link to the original post *</span>
          <input v-model="shareForm.apply_url" type="url" required placeholder="https://" autofocus />
          <small v-if="shareForm.errors.apply_url" class="error">{{ shareForm.errors.apply_url }}</small>
        </label>

        <label class="field">
          <span>Position *</span>
          <input v-model="shareForm.title" type="text" required maxlength="200" placeholder="e.g. IT Officer I" />
          <small v-if="shareForm.errors.title" class="error">{{ shareForm.errors.title }}</small>
        </label>

        <div class="share-row">
          <label class="field">
            <span>Employer</span>
            <input v-model="shareForm.employer" type="text" maxlength="160" />
            <small v-if="shareForm.errors.employer" class="error">{{ shareForm.errors.employer }}</small>
          </label>
          <label class="field">
            <span>Location</span>
            <input v-model="shareForm.location" type="text" maxlength="160" placeholder="City or province" />
          </label>
        </div>

        <div class="share-row">
          <label class="field">
            <span>Type</span>
            <select v-model="shareForm.employment_type">
              <option value="">Not stated</option>
              <option v-for="value in options.types" :key="value" :value="value">{{ TYPE_LABELS[value] }}</option>
            </select>
          </label>
          <label class="field">
            <span>Sector</span>
            <select v-model="shareForm.sector">
              <option value="">Not sure</option>
              <option v-for="value in options.sectors" :key="value" :value="value">{{ SECTOR_LABELS[value] }}</option>
            </select>
          </label>
        </div>

        <div class="share-row">
          <label class="field">
            <span>Salary, as posted</span>
            <input v-model="shareForm.salary_text" type="text" maxlength="120" placeholder="Leave blank if not stated" />
          </label>
          <label class="field">
            <span>Deadline</span>
            <input v-model="shareForm.deadline" type="date" :min="today" />
            <small v-if="shareForm.errors.deadline" class="error">{{ shareForm.errors.deadline }}</small>
          </label>
        </div>

        <label class="check">
          <input v-model="shareForm.is_remote" type="checkbox" />
          <span>Can be done remotely</span>
        </label>

        <label class="field">
          <span>Details</span>
          <textarea
            v-model="shareForm.description"
            rows="4"
            maxlength="8000"
            placeholder="Qualifications, requirements, how to apply — copy what the post says. Don't guess what it doesn't."
          ></textarea>
          <small v-if="shareForm.errors.description" class="error">{{ shareForm.errors.description }}</small>
        </label>

        <TurnstileWidget v-if="!canTrack" @verified="(token) => (shareForm.captchaToken = token)" />
        <small v-if="shareForm.errors.captchaToken" class="error">{{ shareForm.errors.captchaToken }}</small>

        <div class="share-actions">
          <button type="button" class="btn btn-plain" @click="closeShare">Cancel</button>
          <button type="submit" class="btn btn-green" :disabled="shareForm.processing">
            {{ shareForm.processing ? 'Sending…' : 'Send for review' }}
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<style scoped>
.jobs {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: jobs / inline-size;
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

.btn:disabled {
  opacity: 0.6;
  cursor: progress;
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

.btn-sm {
  padding: 0.5rem 0.85rem;
  font-size: 0.82rem;
}

.btn-block {
  width: 100%;
  margin-top: 0.9rem;
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

.link-btn {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-weight: 650;
  color: var(--d-green-text);
  cursor: pointer;
}

.rise {
  animation: rise 0.5s var(--b-ease) backwards;
  animation-delay: calc(var(--i, 0) * 60ms);
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
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

/* --- Filters -------------------------------------------------------------- */

.filters {
  display: flex;
  align-items: flex-end;
  gap: 0.65rem;
  flex-wrap: wrap;
  padding: 0.9rem 0.95rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.field-grow {
  flex: 1 1 14rem;
}

/* Agency names run long ("Northern Philippines Root Crops Research…"); the
   closed select stays a sensible width and the open list shows them whole. */
.field-employer {
  flex: 0 1 13rem;
}

.field-employer select {
  text-overflow: ellipsis;
}

.field > span {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.field input,
.field select,
.field textarea {
  width: 100%;
  min-width: 0;
  padding: 0.55rem 0.7rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.86rem;
  transition: border-color 0.15s ease;
}

.field textarea {
  resize: vertical;
  line-height: 1.5;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--d-green);
}

.field input::placeholder,
.field textarea::placeholder {
  color: var(--d-faint);
}

.check {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.55rem 0;
  font-size: 0.84rem;
  font-weight: 600;
  color: var(--d-ink-2);
  cursor: pointer;
}

.check input {
  width: 1rem;
  height: 1rem;
  accent-color: var(--d-green);
}

.error {
  font-size: 0.76rem;
  color: #dc2626;
}

/* --- List ----------------------------------------------------------------- */

.section-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin: 0.6rem 0 0.1rem;
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

.job-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.job-list > li {
  min-width: 0;
}

.job-list :deep(.job) {
  height: 100%;
}

.pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.9rem;
  font-size: 0.82rem;
  color: var(--d-muted);
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

.rail-promo p,
.rail-note {
  margin: 0.5rem 0 0;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.rail-note {
  font-size: 0.74rem;
  color: var(--d-faint);
}

.rail-rank {
  margin: 0;
  padding: 0;
  list-style: none;
}

.rail-rank li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.6rem;
  padding: 0.38rem 0;
  border-bottom: 1px dashed var(--d-line);
  font-size: 0.82rem;
  color: var(--d-ink-2);
}

.rail-rank li:last-child {
  border-bottom: 0;
}

.rail-rank span,
.rail-rank button {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.rail-rank button {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.rail-rank button:hover {
  color: var(--d-green-text);
}

.rail-rank b {
  font-variant-numeric: tabular-nums;
  color: var(--d-ink);
}

.rail-stats {
  margin: 0;
}

.rail-stats div {
  display: flex;
  justify-content: space-between;
  padding: 0.38rem 0;
  border-bottom: 1px dashed var(--d-line);
  font-size: 0.82rem;
}

.rail-stats div:last-child {
  border-bottom: 0;
}

.rail-stats dt {
  color: var(--d-muted);
}

.rail-stats dd {
  margin: 0;
  font-weight: 700;
  color: var(--d-ink);
  font-variant-numeric: tabular-nums;
}

.rail-tips {
  margin: 0;
  padding-left: 1rem;
  font-size: 0.8rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.rail-tips li + li {
  margin-top: 0.35rem;
}

/* --- Share modal ---------------------------------------------------------- */

.share-scrim {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 4vh 1rem;
  overflow-y: auto;
  background: var(--d-scrim);
}

.share {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  width: 100%;
  max-width: 38rem;
  padding: 1.35rem 1.4rem 1.2rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-xl);
  box-shadow: var(--d-shadow-lift);
  color: var(--d-ink);
  font-family: var(--b-sans);
}

.share-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.share-head h2 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.share-head p {
  margin: 0.35rem 0 0;
  font-size: 0.84rem;
  line-height: 1.5;
  color: var(--d-muted);
}

.share-close {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  color: var(--d-muted);
  font-size: 1.2rem;
  line-height: 1;
  cursor: pointer;
}

.share-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.7rem;
}

.share-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.3rem;
}

.jobs-toast {
  position: fixed;
  left: 50%;
  bottom: 1.5rem;
  z-index: 70;
  max-width: min(32rem, calc(100vw - 2rem));
  margin: 0;
  padding: 0.7rem 1rem;
  transform: translateX(-50%);
  border-radius: var(--d-r-md);
  background: var(--d-ink);
  color: var(--d-card);
  font-family: var(--b-sans);
  font-size: 0.85rem;
  font-weight: 600;
  box-shadow: var(--d-shadow-lift);
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s var(--b-ease);
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, 8px);
}

/* --- Responsive ----------------------------------------------------------- */

@container jobs (max-width: 700px) {
  .job-list {
    grid-template-columns: minmax(0, 1fr);
  }

  .hero h1 {
    font-size: 2rem;
  }
}

@container jobs (max-width: 540px) {
  .hero h1 {
    font-size: 1.7rem;
  }

  .section-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.4rem;
  }

  .filters > .field {
    flex: 1 1 100%;
  }
}

@media (max-width: 540px) {
  .share-row {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (prefers-reduced-motion: reduce) {
  .rise {
    animation: none;
  }
}
</style>
