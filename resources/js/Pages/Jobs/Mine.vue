<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';
import JobCard from '../../Components/JobCard.vue';
import { deadlineLabel, formatDay, TYPE_LABELS } from '../../lib/jobs';

// A student's own corner of JobRadar: what their monitors found, the jobs
// they saved and how each application is going, and the monitors themselves.

const props = defineProps({
  saves: { type: Array, default: () => [] },
  monitors: { type: Array, default: () => [] },
  hits: { type: Array, default: () => [] },
  savedIds: { type: Array, default: () => [] },
  statuses: { type: Object, required: true },
  options: { type: Object, required: true },
});

const page = usePage();

const TABS = [
  { id: 'matches', label: 'Matches' },
  { id: 'saved', label: 'Saved & applied' },
  { id: 'monitors', label: 'Monitors' },
];

const tab = ref('matches');

onMounted(() => {
  const hash = window.location.hash.replace('#', '');
  if (TABS.some((item) => item.id === hash)) tab.value = hash;
});

function selectTab(id) {
  tab.value = id;
  history.replaceState(null, '', `#${id}`);
}

const search = ref('');
const term = computed(() => search.value.trim().toLowerCase());

function matchesTerm(listing) {
  if (!term.value) return true;
  return [listing.title, listing.employer, listing.location].join(' ').toLowerCase().includes(term.value);
}

const saved = computed(() => new Set(props.savedIds));
const newCount = computed(() => props.hits.filter((hit) => hit.is_new).length);
const visibleHits = computed(() => props.hits.filter((hit) => matchesTerm(hit.listing)));
const visibleSaves = computed(() => props.saves.filter((item) => matchesTerm(item.listing)));

const pipeline = computed(() => {
  const counts = {};
  for (const item of props.saves) counts[item.status] = (counts[item.status] ?? 0) + 1;
  return counts;
});

const upcoming = computed(() =>
  props.saves
    .filter((item) => item.follow_up_on || item.listing.deadline)
    .map((item) => ({
      id: item.id,
      listingId: item.listing.id,
      title: item.listing.title,
      date: item.follow_up_on ?? item.listing.deadline,
      kind: item.follow_up_on ? 'Follow up' : deadlineLabel(item.listing.deadline),
    }))
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(0, 5),
);

function toggleSave(listing) {
  const options = { preserveScroll: true, preserveState: true };

  if (saved.value.has(listing.id)) router.delete(`/jobs/${listing.id}/save`, options);
  else router.post(`/jobs/${listing.id}/save`, {}, options);
}

function setStatus(item, status) {
  router.patch(`/job-saves/${item.id}`, { status }, { preserveScroll: true, preserveState: true });
}

function removeSave(item) {
  router.delete(`/job-saves/${item.id}`, { preserveScroll: true, preserveState: true });
}

// --- Monitor form ------------------------------------------------------

const editing = ref(null);

const blank = () => ({
  name: '',
  keywords: '',
  location: '',
  employment_types: [],
  remote_ok: true,
  min_score: 60,
  frequency: 'daily',
  email_alerts: true,
  is_active: true,
});

const monitorForm = useForm(blank());

const atLimit = computed(() => props.monitors.length >= props.options.maxMonitors);

function editMonitor(monitor) {
  editing.value = monitor.id;
  monitorForm.defaults({
    ...monitor,
    keywords: monitor.keywords.join(', '),
    location: monitor.location ?? '',
    employment_types: monitor.employment_types ?? [],
  });
  monitorForm.reset();
  monitorForm.clearErrors();
}

function cancelEdit() {
  editing.value = null;
  monitorForm.defaults(blank());
  monitorForm.reset();
  monitorForm.clearErrors();
}

function submitMonitor() {
  const options = { preserveScroll: true, onSuccess: cancelEdit };

  if (editing.value) monitorForm.patch(`/job-monitors/${editing.value}`, options);
  else monitorForm.post('/job-monitors', options);
}

function toggleMonitor(monitor, field) {
  router.patch(
    `/job-monitors/${monitor.id}`,
    { ...monitor, [field]: !monitor[field] },
    { preserveScroll: true, preserveState: true },
  );
}

function deleteMonitor(monitor) {
  if (!window.confirm(`Delete the monitor “${monitor.name}”? Its matches go with it.`)) return;
  router.delete(`/job-monitors/${monitor.id}`, { preserveScroll: true, preserveState: true });
}

// --- Flash -------------------------------------------------------------

const toast = ref('');

watch(
  () => page.props.flash?.success,
  (message) => {
    if (!message) return;
    toast.value = message;
    setTimeout(() => (toast.value = ''), 5000);
  },
  { immediate: true },
);
</script>

<template>
  <Head title="My jobs" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search my jobs">
    <div class="mine">
      <header class="hero">
        <h1>My jobs</h1>
        <p class="hero-lede">
          What your monitors found, what you saved, and where each application stands. Only you can see this page.
        </p>
      </header>

      <div class="tabs" role="tablist">
        <button
          v-for="item in TABS"
          :key="item.id"
          type="button"
          role="tab"
          :aria-selected="tab === item.id"
          :class="{ active: tab === item.id }"
          @click="selectTab(item.id)"
        >
          {{ item.label }}
          <span v-if="item.id === 'matches' && newCount" class="badge">{{ newCount }} new</span>
          <span v-else-if="item.id === 'saved' && saves.length" class="badge is-quiet">{{ saves.length }}</span>
          <span v-else-if="item.id === 'monitors' && monitors.length" class="badge is-quiet">{{ monitors.length }}</span>
        </button>
      </div>

      <!-- Matches -->
      <section v-if="tab === 'matches'" class="panel">
        <div v-if="!monitors.length" class="empty">
          No monitors yet. Tell JobRadar what you're looking for and matches land here.
          <button type="button" class="link-btn" @click="selectTab('monitors')">Create a monitor</button>
        </div>
        <div v-else-if="!visibleHits.length" class="empty">
          {{ term ? `No matches for “${search}”.` : 'Nothing matched yet. Your monitors check every hour.' }}
        </div>
        <ul v-else class="job-list">
          <li v-for="hit in visibleHits" :key="hit.id" :class="{ 'is-new': hit.is_new }">
            <p class="hit-meta">
              <span v-if="hit.is_new" class="new-dot">New</span>
              via {{ hit.monitor }}
            </p>
            <JobCard
              :listing="hit.listing"
              :score="hit.score"
              :reasons="hit.reasons"
              can-save
              :saved="saved.has(hit.listing.id)"
              @toggle-save="toggleSave"
            />
          </li>
        </ul>
      </section>

      <!-- Saved -->
      <section v-else-if="tab === 'saved'" class="panel">
        <div v-if="!visibleSaves.length" class="empty">
          {{ term ? `No saved jobs match “${search}”.` : 'No saved jobs yet. Tap the bookmark on any job to keep it here.' }}
          <Link v-if="!term" href="/jobs" class="link-btn">Browse jobs</Link>
        </div>
        <ul v-else class="tracker-list">
          <li v-for="item in visibleSaves" :key="item.id" class="tracker-row" :class="{ 'is-closed': item.listing.status === 'closed' }">
            <div class="tracker-main">
              <Link :href="`/jobs/${item.listing.id}`" class="tracker-title">{{ item.listing.title }}</Link>
              <p class="tracker-sub">
                {{ item.listing.employer || 'Employer not stated' }}
                <template v-if="item.listing.location"> · {{ item.listing.location }}</template>
                <template v-if="item.listing.employment_type"> · {{ TYPE_LABELS[item.listing.employment_type] }}</template>
              </p>
              <p class="tracker-dates">
                <span v-if="item.applied_on">Applied {{ formatDay(item.applied_on) }}</span>
                <span v-if="item.follow_up_on">Follow up {{ formatDay(item.follow_up_on) }}</span>
                <span v-if="deadlineLabel(item.listing.deadline)">{{ deadlineLabel(item.listing.deadline) }}</span>
                <span v-if="item.listing.status === 'closed'" class="closed">Listing closed</span>
              </p>
              <p v-if="item.notes" class="tracker-notes">{{ item.notes }}</p>
            </div>
            <div class="tracker-side">
              <select
                :value="item.status"
                :aria-label="`Status for ${item.listing.title}`"
                @change="setStatus(item, $event.target.value)"
              >
                <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
              </select>
              <Link :href="`/jobs/${item.listing.id}`" class="btn btn-plain btn-sm">Notes &amp; dates</Link>
              <button type="button" class="link-btn is-muted" @click="removeSave(item)">Remove</button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Monitors -->
      <section v-else class="panel">
        <ul v-if="monitors.length" class="monitor-list">
          <li v-for="monitor in monitors" :key="monitor.id" class="monitor" :class="{ 'is-paused': !monitor.is_active }">
            <div class="monitor-main">
              <p class="monitor-name">
                <b>{{ monitor.name }}</b>
                <span v-if="!monitor.is_active" class="badge is-quiet">Paused</span>
              </p>
              <p class="monitor-terms">
                {{ monitor.keywords.join(', ') }}
                <template v-if="monitor.location"> · in {{ monitor.location }}</template>
                <template v-if="monitor.employment_types?.length">
                  · {{ monitor.employment_types.map((type) => TYPE_LABELS[type]).join(' / ') }}
                </template>
              </p>
              <p class="monitor-sub">
                {{ monitor.hits_count }} match{{ monitor.hits_count === 1 ? '' : 'es' }} ·
                {{ monitor.min_score }}%+ ·
                {{ monitor.email_alerts ? `${monitor.frequency} email` : 'no email' }}
              </p>
            </div>
            <div class="monitor-actions">
              <button type="button" class="btn btn-plain btn-sm" @click="editMonitor(monitor)">Edit</button>
              <button type="button" class="btn btn-plain btn-sm" @click="toggleMonitor(monitor, 'is_active')">
                {{ monitor.is_active ? 'Pause' : 'Resume' }}
              </button>
              <button type="button" class="link-btn is-muted" @click="deleteMonitor(monitor)">Delete</button>
            </div>
          </li>
        </ul>

        <form v-if="editing || !atLimit" class="monitor-form" @submit.prevent="submitMonitor">
          <h2>{{ editing ? 'Edit monitor' : 'New monitor' }}</h2>

          <label class="field">
            <span>Name</span>
            <input v-model="monitorForm.name" type="text" maxlength="80" placeholder="IT jobs in Baguio" required />
            <small v-if="monitorForm.errors.name" class="error">{{ monitorForm.errors.name }}</small>
          </label>

          <label class="field">
            <span>Job titles or keywords, comma separated</span>
            <input
              v-model="monitorForm.keywords"
              type="text"
              placeholder="Information Systems Analyst, Web Developer, IT Officer"
              required
            />
            <small v-if="monitorForm.errors.keywords" class="error">{{ monitorForm.errors.keywords }}</small>
            <small v-else class="hint">Up to {{ options.maxKeywords }}. A job has to mention at least one to count.</small>
          </label>

          <label class="field">
            <span>Location</span>
            <input v-model="monitorForm.location" type="text" maxlength="120" placeholder="Baguio City and Benguet" />
            <small class="hint">Leave blank for anywhere. Separate places with “and” or a comma.</small>
          </label>

          <fieldset class="field">
            <span>Employment type</span>
            <div class="chips">
              <label v-for="type in options.types" :key="type" class="chip" :class="{ active: monitorForm.employment_types.includes(type) }">
                <input v-model="monitorForm.employment_types" type="checkbox" :value="type" />
                {{ TYPE_LABELS[type] }}
              </label>
            </div>
            <small class="hint">None ticked means any type.</small>
          </fieldset>

          <div class="form-row">
            <label class="field">
              <span>Minimum match: {{ monitorForm.min_score }}%</span>
              <input v-model.number="monitorForm.min_score" type="range" min="20" max="100" step="5" />
            </label>
            <label class="field">
              <span>Email digest</span>
              <select v-model="monitorForm.frequency" :disabled="!monitorForm.email_alerts">
                <option v-for="frequency in options.frequencies" :key="frequency" :value="frequency">
                  {{ frequency === 'daily' ? 'Daily' : 'Weekly' }}
                </option>
              </select>
            </label>
          </div>

          <div class="checks">
            <label class="check"><input v-model="monitorForm.remote_ok" type="checkbox" /> Include remote jobs</label>
            <label class="check"><input v-model="monitorForm.email_alerts" type="checkbox" /> Email me new matches</label>
          </div>

          <div class="form-actions">
            <button v-if="editing" type="button" class="btn btn-plain" @click="cancelEdit">Cancel</button>
            <button type="submit" class="btn btn-green" :disabled="monitorForm.processing">
              {{ editing ? 'Save changes' : 'Create monitor' }}
            </button>
          </div>
        </form>

        <p v-else class="empty">You have {{ options.maxMonitors }} monitors, the most allowed. Delete one to add another.</p>
      </section>
    </div>

    <template #rail>
      <p class="rail-stat">
        <span>Saved</span>
        <strong>{{ saves.length }}</strong>
        <i>· {{ (pipeline.applied ?? 0) + (pipeline['under-review'] ?? 0) + (pipeline.interview ?? 0) }} in progress</i>
      </p>

      <section v-if="saves.length" class="rail-card">
        <div class="rail-head">
          <h2>Applications</h2>
        </div>
        <dl class="rail-stats">
          <div v-for="(label, value) in statuses" v-show="pipeline[value]" :key="value">
            <dt>{{ label }}</dt>
            <dd>{{ pipeline[value] }}</dd>
          </div>
        </dl>
      </section>

      <section v-if="upcoming.length" class="rail-card">
        <div class="rail-head">
          <h2>Coming up</h2>
        </div>
        <ul class="rail-rank">
          <li v-for="item in upcoming" :key="item.id">
            <Link :href="`/jobs/${item.listingId}`">{{ item.title }}</Link>
            <small>{{ item.kind }} · {{ formatDay(item.date) }}</small>
          </li>
        </ul>
      </section>

      <section class="rail-card rail-promo">
        <h2>How matching works</h2>
        <p>
          Each job is scored out of 100: the keyword in the job title counts most, then location, employment
          type, and how new the posting is. Jobs below your minimum are left out.
        </p>
        <Link href="/jobs" class="btn btn-green btn-block">Browse all jobs <span aria-hidden="true">&rarr;</span></Link>
      </section>
    </template>
  </DirectoryLayout>

  <Teleport to="body">
    <Transition name="toast">
      <p v-if="toast" class="jobs-toast" role="status">{{ toast }}</p>
    </Transition>
  </Teleport>
</template>

<style scoped>
.mine {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: mine / inline-size;
}

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
  padding: 0.45rem 0.8rem;
  font-size: 0.8rem;
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
  text-decoration: none;
  cursor: pointer;
}

.link-btn.is-muted {
  font-size: 0.8rem;
  color: var(--d-muted);
}

.hero {
  padding: 1.5rem 0 0.25rem;
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
  margin: 0.7rem 0 0;
  max-width: 46ch;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--d-muted);
}

.tabs {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.tabs button {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.9rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  color: var(--d-muted);
  font: inherit;
  font-size: 0.84rem;
  font-weight: 650;
  cursor: pointer;
  transition: border-color 0.15s ease, color 0.15s ease;
}

.tabs button:hover {
  border-color: var(--d-green);
}

.tabs button.active {
  background: var(--d-green);
  border-color: var(--d-green);
  color: var(--d-green-ink);
}

.badge {
  padding: 0.05rem 0.45rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green-tint);
  color: var(--d-green-text);
  font-size: 0.7rem;
  font-weight: 700;
}

.badge.is-quiet {
  background: var(--d-soft);
  color: var(--d-muted);
}

.tabs button.active .badge {
  background: color-mix(in srgb, currentColor 20%, transparent);
  color: inherit;
}

.panel {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

/* --- Matches -------------------------------------------------------------- */

.job-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.job-list > li {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.job-list :deep(.job) {
  flex: 1;
}

.hit-meta {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0 0 0 0.2rem;
  font-size: 0.72rem;
  color: var(--d-faint);
}

.new-dot {
  padding: 0.05rem 0.4rem;
  border-radius: var(--d-r-pill);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.64rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.is-new :deep(.job) {
  border-color: var(--d-green-tint-line);
}

/* --- Tracker -------------------------------------------------------------- */

.tracker-list,
.monitor-list {
  margin: 0;
  padding: 0;
  list-style: none;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.tracker-row,
.monitor {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.95rem 1.05rem;
  border-bottom: 1px solid var(--d-line);
}

.tracker-row:last-child,
.monitor:last-child {
  border-bottom: 0;
}

.tracker-row.is-closed .tracker-title {
  color: var(--d-muted);
}

.tracker-main,
.monitor-main {
  min-width: 0;
}

.tracker-title {
  font-size: 0.98rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
  text-decoration: none;
}

.tracker-title:hover {
  color: var(--d-green-text);
}

.tracker-sub,
.monitor-terms {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  color: var(--d-ink-2);
}

.tracker-dates,
.monitor-sub {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 0.75rem;
  margin: 0.35rem 0 0;
  font-size: 0.74rem;
  color: var(--d-faint);
}

.tracker-dates .closed {
  font-weight: 650;
  color: #c53030;
}

.tracker-notes {
  margin: 0.45rem 0 0;
  padding-left: 0.6rem;
  border-left: 2px solid var(--d-line);
  white-space: pre-line;
  font-size: 0.8rem;
  color: var(--d-muted);
}

.tracker-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.45rem;
  flex-shrink: 0;
}

.tracker-side select {
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.82rem;
}

/* --- Monitors ------------------------------------------------------------- */

.monitor.is-paused .monitor-main {
  opacity: 0.6;
}

.monitor-name {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  margin: 0;
  font-size: 0.98rem;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.monitor-actions {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
}

.monitor-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.2rem 1.25rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.monitor-form h2 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--d-ink);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.field > span {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.field input[type='text'],
.field select {
  width: 100%;
  min-width: 0;
  padding: 0.55rem 0.7rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.86rem;
}

.field input[type='range'] {
  accent-color: var(--d-green);
}

.field input:focus,
.field select:focus {
  outline: none;
  border-color: var(--d-green);
}

.hint {
  font-size: 0.74rem;
  color: var(--d-faint);
}

.error {
  font-size: 0.76rem;
  color: #dc2626;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--d-muted);
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.chip input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.chip:hover,
.chip:focus-within {
  border-color: var(--d-green);
}

.chip.active {
  background: var(--d-green);
  border-color: var(--d-green);
  color: var(--d-green-ink);
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}

.checks {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem 1.2rem;
}

.check {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
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

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
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

.rail-rank {
  margin: 0;
  padding: 0;
  list-style: none;
}

.rail-rank li {
  display: flex;
  flex-direction: column;
  padding: 0.4rem 0;
  border-bottom: 1px dashed var(--d-line);
}

.rail-rank li:last-child {
  border-bottom: 0;
}

.rail-rank :deep(a) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.82rem;
  font-weight: 650;
  color: var(--d-ink);
  text-decoration: none;
}

.rail-rank small {
  font-size: 0.72rem;
  color: var(--d-muted);
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
  transition: opacity 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
}

@container mine (max-width: 700px) {
  .job-list {
    grid-template-columns: minmax(0, 1fr);
  }

  .hero h1 {
    font-size: 2rem;
  }
}

@container mine (max-width: 560px) {
  .tracker-row,
  .monitor {
    flex-direction: column;
  }

  .tracker-side {
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
  }

  .form-row {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
