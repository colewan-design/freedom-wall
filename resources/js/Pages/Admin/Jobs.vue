<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { adminApi } from '../../lib/adminApi';
import { formatDateTime } from '../../lib/date';
import { hostOf, SECTOR_LABELS, TYPE_LABELS } from '../../lib/jobs';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  drivers: { type: Object, required: true },
  types: { type: Array, required: true },
  sectors: { type: Array, required: true },
});

const banner = reactive({ message: '', tone: 'info' });

function showBanner(message, tone = 'info') {
  banner.message = message;
  banner.tone = tone;
}

// --- Queue -------------------------------------------------------------

const STATUSES = [
  { id: 'pending', label: 'Waiting' },
  { id: 'approved', label: 'Live' },
  { id: 'closed', label: 'Closed' },
  { id: 'rejected', label: 'Rejected' },
];

const status = ref('pending');
const listings = ref([]);
const counts = ref({});
const page = ref(1);
const totalPages = ref(1);
const loading = ref(true);
const actingId = ref(null);
const edits = reactive({});

async function loadListings() {
  loading.value = true;

  try {
    const data = await adminApi.getJobListings(status.value, page.value);
    listings.value = data.items;
    counts.value = data.counts;
    totalPages.value = data.totalPages;

    for (const item of data.items) {
      edits[item.id] = {
        title: item.title,
        employer: item.employer ?? '',
        location: item.location ?? '',
        employment_type: item.employment_type ?? '',
        sector: item.sector ?? '',
        salary_text: item.salary_text ?? '',
      };
    }
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    loading.value = false;
  }
}

function switchStatus(id) {
  status.value = id;
  page.value = 1;
  loadListings();
}

function goTo(next) {
  page.value = next;
  loadListings();
}

async function act(listing, action) {
  actingId.value = listing.id;

  try {
    if (action === 'approve') {
      const edit = edits[listing.id];
      const data = await adminApi.approveJob(listing.id, {
        ...edit,
        employer: edit.employer || null,
        location: edit.location || null,
        employment_type: edit.employment_type || null,
        sector: edit.sector || null,
        salary_text: edit.salary_text || null,
      });
      showBanner(data.matched ? `Approved. ${data.matched} monitor(s) will hear about it.` : 'Approved.', 'success');
    } else if (action === 'reject') {
      await adminApi.rejectJob(listing.id);
      showBanner('Rejected.', 'info');
    } else {
      await adminApi.closeJob(listing.id);
      showBanner('Closed. It stays readable for anyone who saved it.', 'info');
    }

    await loadListings();
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    actingId.value = null;
  }
}

// --- Sources -----------------------------------------------------------

// What each driver needs, so the form only asks for the fields that apply.
// Where a posting may be based, so a remote board does not fill the page with
// US-only jobs. Shared by every driver that reads more than one place.
const LOCATIONS_FIELD = {
  key: 'locations',
  label: 'Only keep jobs located in (comma separated; blank = all)',
  placeholder: 'Philippines, Asia, APAC, Worldwide, Anywhere',
};

const DRIVER_FIELDS = {
  rss: [
    { key: 'url', label: 'Feed URL', placeholder: 'https://example.gov.ph/careers/feed', required: true },
    { key: 'employer', label: 'Employer (if the feed is one employer)' },
    { key: 'location', label: 'Default location' },
    { key: 'keywords', label: 'Only keep items mentioning (comma separated)', placeholder: 'IT, programmer, analyst' },
    LOCATIONS_FIELD,
    { key: 'remote', label: 'Every job in this feed is remote', type: 'checkbox' },
    { key: 'employer_in_title', label: 'Titles read “Company: Job title”', type: 'checkbox' },
  ],
  remotive: [
    { key: 'search', label: 'Search term', placeholder: 'developer' },
    { key: 'category', label: 'Category slug', placeholder: 'software-dev' },
    { key: 'limit', label: 'Max per read', placeholder: '50' },
    LOCATIONS_FIELD,
  ],
  greenhouse: [
    { key: 'board', label: 'Board token', placeholder: 'from boards.greenhouse.io/<token>', required: true },
    { key: 'employer', label: 'Employer name' },
    LOCATIONS_FIELD,
  ],
  lever: [
    { key: 'company', label: 'Company slug', placeholder: 'from jobs.lever.co/<company>', required: true },
    { key: 'employer', label: 'Employer name' },
    LOCATIONS_FIELD,
  ],
  page: [
    { key: 'url', label: 'Page that lists the openings', placeholder: 'https://hris.baguio.gov.ph/jobs', required: true },
    { key: 'link_contains', label: 'Every job link contains', placeholder: '/jobs/', required: true },
    { key: 'employer', label: 'Employer', placeholder: 'City Government of Baguio' },
    { key: 'location', label: 'Location', placeholder: 'Baguio City' },
  ],
};

const sources = ref([]);
const loadingSources = ref(true);
const fetchingId = ref(null);
const editingSourceId = ref(null);

const blankSource = () => ({ name: '', driver: 'rss', config: {}, interval: 60, is_active: true, auto_approve: false });
const sourceForm = reactive(blankSource());
const savingSource = ref(false);

const fields = computed(() => DRIVER_FIELDS[sourceForm.driver] ?? []);

async function loadSources() {
  loadingSources.value = true;

  try {
    sources.value = (await adminApi.getJobSources()).items;
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    loadingSources.value = false;
  }
}

function editSource(source) {
  editingSourceId.value = source.id;
  Object.assign(sourceForm, {
    name: source.name,
    driver: source.driver,
    config: {
      ...source.config,
      keywords: Array.isArray(source.config.keywords) ? source.config.keywords.join(', ') : source.config.keywords,
      locations: Array.isArray(source.config.locations) ? source.config.locations.join(', ') : source.config.locations,
    },
    interval: source.config.interval_minutes ?? 60,
    is_active: source.is_active,
    auto_approve: source.auto_approve,
  });
}

function resetSourceForm() {
  editingSourceId.value = null;
  Object.assign(sourceForm, blankSource());
}

async function saveSource() {
  savingSource.value = true;

  const body = {
    name: sourceForm.name,
    driver: sourceForm.driver,
    config: { ...sourceForm.config, interval_minutes: Number(sourceForm.interval) || 60 },
    is_active: sourceForm.is_active,
    auto_approve: sourceForm.auto_approve,
  };

  try {
    if (editingSourceId.value) {
      await adminApi.updateJobSource(editingSourceId.value, body);
      showBanner('Source updated.', 'success');
    } else {
      await adminApi.createJobSource(body);
      showBanner('Source added. It will be read on the next hourly run, or press Read now.', 'success');
    }

    resetSourceForm();
    await loadSources();
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    savingSource.value = false;
  }
}

async function toggleSource(source) {
  try {
    await adminApi.updateJobSource(source.id, { is_active: !source.is_active });
    await loadSources();
  } catch (err) {
    showBanner(err.message, 'error');
  }
}

async function removeSource(source) {
  if (!window.confirm(`Remove “${source.name}”? Listings it found stay up.`)) return;

  try {
    await adminApi.deleteJobSource(source.id);
    await loadSources();
  } catch (err) {
    showBanner(err.message, 'error');
  }
}

async function fetchNow(source) {
  fetchingId.value = source.id;

  try {
    const { counts: result, found } = await adminApi.fetchJobSource(source.id);
    showBanner(
      `${source.name}: read ${found} posting(s) — ${result.new} new, ${result.merged} merged into existing, ${result.seen} already known.`,
      'success',
    );
    await Promise.all([loadSources(), loadListings()]);
  } catch (err) {
    showBanner(`${source.name}: ${err.message}`, 'error');
    await loadSources();
  } finally {
    fetchingId.value = null;
  }
}

onMounted(() => {
  loadListings();
  loadSources();
});
</script>

<template>
  <Head title="Jobs queue" />

  <section class="dashboard">
    <div class="header-row">
      <div>
        <h1>JobRadar</h1>
        <p class="subtext">
          Approve shared links and feed postings before they go public. Approving is what lets monitors hear about a job.
        </p>
      </div>
      <Link href="/admin/dashboard" class="btn btn-secondary btn-sm">&larr; Wall queue</Link>
    </div>

    <Transition name="banner">
      <p v-if="banner.message" class="banner" :class="banner.tone">{{ banner.message }}</p>
    </Transition>

    <section class="panel">
      <div class="panel-head">
        <h2>Listings</h2>
        <div class="view-toggle" role="group" aria-label="Listing status">
          <button
            v-for="item in STATUSES"
            :key="item.id"
            type="button"
            class="view-btn"
            :class="{ active: status === item.id }"
            @click="switchStatus(item.id)"
          >
            {{ item.label }} <span class="count">{{ counts[item.id] ?? 0 }}</span>
          </button>
        </div>
      </div>

      <p v-if="loading" class="hint">Loading…</p>
      <p v-else-if="!listings.length" class="hint">
        {{ status === 'pending' ? 'Nothing waiting. The queue is clear.' : 'Nothing here.' }}
      </p>

      <ul v-else class="queue">
        <li v-for="listing in listings" :key="listing.id" class="item" :class="{ flagged: listing.flags.length }">
          <p class="report-meta">
            <span class="report-tag">{{ listing.community ? 'shared link' : 'feed' }}</span>
            <span v-for="flag in listing.flags" :key="flag.kind" class="report-reason" :title="flag.evidence">
              {{ flag.label }}<template v-if="flag.kind === 'reported'"> ×{{ flag.evidence }}</template>
            </span>
            <time :datetime="listing.discovered_at">{{ formatDateTime(listing.discovered_at) }}</time>
            <span v-if="listing.saves_count">{{ listing.saves_count }} saved</span>
          </p>

          <template v-if="status === 'pending'">
            <label class="mini-field wide">
              <span>Title</span>
              <input v-model="edits[listing.id].title" type="text" maxlength="200" />
            </label>
            <div class="mini-grid">
              <label class="mini-field"><span>Employer</span><input v-model="edits[listing.id].employer" type="text" /></label>
              <label class="mini-field"><span>Location</span><input v-model="edits[listing.id].location" type="text" /></label>
              <label class="mini-field">
                <span>Type</span>
                <select v-model="edits[listing.id].employment_type">
                  <option value="">Not stated</option>
                  <option v-for="type in types" :key="type" :value="type">{{ TYPE_LABELS[type] }}</option>
                </select>
              </label>
              <label class="mini-field">
                <span>Sector</span>
                <select v-model="edits[listing.id].sector">
                  <option value="">Unknown</option>
                  <option v-for="sector in sectors" :key="sector" :value="sector">{{ SECTOR_LABELS[sector] }}</option>
                </select>
              </label>
              <label class="mini-field wide"><span>Salary</span><input v-model="edits[listing.id].salary_text" type="text" /></label>
            </div>
          </template>
          <template v-else>
            <p class="job-title">{{ listing.title }}</p>
            <p class="job-sub">
              {{ listing.employer || 'No employer' }} · {{ listing.location || (listing.is_remote ? 'Remote' : 'No location') }}
            </p>
          </template>

          <p v-if="listing.description" class="desc">{{ listing.description }}</p>

          <p class="links">
            <a v-for="source in listing.sources" :key="source.id" :href="source.url" target="_blank" rel="noopener noreferrer">
              {{ source.label }} ({{ hostOf(source.url) }}) &nearr;
            </a>
          </p>

          <div class="actions">
            <a v-if="listing.status !== 'pending'" :href="`/jobs/${listing.id}`" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
              Open listing
            </a>
            <button
              v-if="listing.status !== 'approved'"
              class="btn btn-approve btn-sm"
              :disabled="actingId === listing.id"
              @click="act(listing, 'approve')"
            >
              {{ listing.status === 'pending' ? 'Approve' : 'Put back up' }}
            </button>
            <button
              v-if="listing.status === 'approved'"
              class="btn btn-secondary btn-sm"
              :disabled="actingId === listing.id"
              @click="act(listing, 'close')"
            >
              Mark closed
            </button>
            <button
              v-if="listing.status !== 'rejected'"
              class="btn btn-reject btn-sm"
              :disabled="actingId === listing.id"
              @click="act(listing, 'reject')"
            >
              Reject
            </button>
          </div>
        </li>
      </ul>

      <div v-if="totalPages > 1" class="pager">
        <button class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="goTo(page - 1)">Previous</button>
        <span>Page {{ page }} of {{ totalPages }}</span>
        <button class="btn btn-secondary btn-sm" :disabled="page >= totalPages" @click="goTo(page + 1)">Next</button>
      </div>
    </section>

    <section class="panel">
      <h2>Sources</h2>
      <p class="hint">
        Feeds and official APIs work best. A plain vacancy page can be read too when its robots.txt allows it. Sites
        behind Cloudflare or Imperva bot checks (CSC, DICT, DPWH) refuse automated reading, and Facebook pages and
        groups forbid it; readers can still share those posts as links.
      </p>

      <p v-if="loadingSources" class="hint">Loading…</p>
      <p v-else-if="!sources.length" class="hint">No sources yet. Add one below.</p>

      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Source</th>
              <th>Type</th>
              <th>Last read</th>
              <th>Found</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="source in sources" :key="source.id" :class="{ muted: !source.is_active }">
              <td>
                <b>{{ source.name }}</b>
                <small v-if="source.auto_approve" class="tag">auto-approve</small>
                <small v-if="!source.is_active" class="tag">paused</small>
                <small v-if="source.last_status === 'error'" class="err">{{ source.last_error }}</small>
              </td>
              <td>{{ drivers[source.driver] ?? source.driver }}</td>
              <td>
                <span v-if="source.last_fetched_at" :class="{ err: source.last_status === 'error' }">
                  {{ formatDateTime(source.last_fetched_at) }}
                </span>
                <span v-else>Never</span>
              </td>
              <td>{{ source.last_found_count }} <small>({{ source.seen_count }} total)</small></td>
              <td class="row-actions">
                <button class="btn btn-approve btn-sm" :disabled="fetchingId === source.id" @click="fetchNow(source)">
                  {{ fetchingId === source.id ? 'Reading…' : 'Read now' }}
                </button>
                <button class="btn btn-secondary btn-sm" @click="editSource(source)">Edit</button>
                <button class="btn btn-secondary btn-sm" @click="toggleSource(source)">{{ source.is_active ? 'Pause' : 'Resume' }}</button>
                <button class="btn btn-reject btn-sm" @click="removeSource(source)">Remove</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <form class="source-form" @submit.prevent="saveSource">
        <h3>{{ editingSourceId ? 'Edit source' : 'Add a source' }}</h3>
        <div class="mini-grid">
          <label class="mini-field">
            <span>Name</span>
            <input v-model="sourceForm.name" type="text" required maxlength="120" placeholder="BSU HR feed" />
          </label>
          <label class="mini-field">
            <span>Kind</span>
            <select v-model="sourceForm.driver" :disabled="Boolean(editingSourceId)">
              <option v-for="(label, key) in drivers" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <template v-for="field in fields" :key="field.key">
            <label v-if="field.type === 'checkbox'" class="mini-check">
              <input v-model="sourceForm.config[field.key]" type="checkbox" />
              {{ field.label }}
            </label>
            <label v-else class="mini-field" :class="{ wide: ['url', 'keywords', 'locations'].includes(field.key) }">
              <span>{{ field.label }}</span>
              <input v-model="sourceForm.config[field.key]" type="text" :placeholder="field.placeholder" :required="field.required" />
            </label>
          </template>
          <label class="mini-field">
            <span>Read every (minutes)</span>
            <input v-model.number="sourceForm.interval" type="number" min="30" max="10080" step="30" />
          </label>
        </div>
        <div class="checks">
          <label><input v-model="sourceForm.is_active" type="checkbox" /> Active</label>
          <label>
            <input v-model="sourceForm.auto_approve" type="checkbox" />
            Skip the queue (only for an employer's own board — flagged posts still wait)
          </label>
        </div>
        <div class="actions">
          <button v-if="editingSourceId" type="button" class="btn btn-secondary btn-sm" @click="resetSourceForm">Cancel</button>
          <button type="submit" class="btn btn-approve btn-sm" :disabled="savingSource">
            {{ editingSourceId ? 'Save source' : 'Add source' }}
          </button>
        </div>
      </form>
    </section>
  </section>
</template>

<style scoped>
.dashboard {
  max-width: 100%;
  margin: 0 auto;
  padding: 0.5rem 0 3rem;
  color: var(--nf-ink);

  --ink: var(--nf-ink);
  --muted: var(--nf-muted);
  --paper: var(--nf-panel);
  --line: var(--nf-line);
  --page-bg: var(--nf-surface-2);
  --accent-soft: rgba(13, 148, 136, 0.18);
  --danger: #f87171;
  --danger-bg: rgba(220, 38, 38, 0.18);
  --success: #22c55e;
  --success-bg: rgba(34, 197, 94, 0.16);
  --info: var(--nf-accent);
  --info-bg: rgba(13, 148, 136, 0.14);
  --nf-accent-dark: var(--b-green-hover);
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

h1 {
  margin: 0;
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.subtext {
  margin: 0.25rem 0 0;
  max-width: 60ch;
  color: var(--muted);
}

.view-toggle {
  display: inline-flex;
  flex-wrap: wrap;
  padding: 0.2rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-thumb);
}

.view-btn {
  border: none;
  background: transparent;
  padding: 0.45rem 0.8rem;
  border-radius: var(--b-r-sm);
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
}

.view-btn.active {
  background: var(--accent-soft);
  color: var(--nf-accent);
}

.count {
  margin-left: 0.25rem;
  font-variant-numeric: tabular-nums;
  opacity: 0.75;
}

.panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.panel h2 {
  margin: 0 0 0.25rem;
  font-size: 1.15rem;
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.panel-head h2 {
  margin: 0;
}

.hint {
  color: var(--muted);
  margin: 0 0 1rem;
}

.banner {
  padding: 0.75rem 1rem;
  border-radius: var(--b-r-md);
  font-size: 0.92rem;
  margin-bottom: 1rem;
}

.banner.error {
  color: var(--danger);
  background: var(--danger-bg);
}

.banner.success {
  color: var(--success);
  background: var(--success-bg);
}

.banner.info {
  color: var(--info);
  background: var(--info-bg);
}

.banner-enter-active,
.banner-leave-active {
  transition: all 0.25s ease;
}

.banner-enter-from,
.banner-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

.queue {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr));
  gap: 0.9rem;
}

.item {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  min-width: 0;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  padding: 1rem;
}

.item.flagged {
  border-color: rgba(220, 38, 38, 0.45);
}

.report-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.55rem;
  margin: 0;
  color: var(--muted);
  font-size: 0.78rem;
}

.report-tag,
.report-reason {
  padding: 1px 7px;
  border: 1px solid var(--line);
  border-radius: var(--b-r-pill);
  font-size: 0.7rem;
  letter-spacing: 0.6px;
  text-transform: uppercase;
}

.report-reason {
  border-color: var(--danger);
  color: var(--danger);
}

.job-title {
  margin: 0;
  font-weight: 700;
}

.job-sub {
  margin: 0;
  font-size: 0.85rem;
  color: var(--muted);
}

.desc {
  max-height: 7.5rem;
  margin: 0;
  overflow-y: auto;
  padding: 0.55rem 0.65rem;
  border: 1px solid var(--line);
  border-radius: var(--b-r-sm);
  background: var(--page-bg);
  white-space: pre-line;
  font-size: 0.82rem;
  line-height: 1.5;
}

.links {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  margin: 0;
  font-size: 0.8rem;
}

.links a {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--nf-accent);
}

.mini-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.mini-field {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}

.mini-field.wide {
  grid-column: 1 / -1;
}

.mini-field span {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--muted);
}

.mini-field input,
.mini-field select {
  width: 100%;
  min-width: 0;
  padding: 0.45rem 0.55rem;
  border: 1px solid var(--line);
  border-radius: var(--b-r-sm);
  background: var(--page-bg);
  color: var(--ink);
  font: inherit;
  font-size: 0.85rem;
}

.mini-check {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  grid-column: 1 / -1;
  font-size: 0.85rem;
}

.mini-check input {
  accent-color: var(--nf-accent);
}

.actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pager {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 1rem;
  font-size: 0.85rem;
  color: var(--muted);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  padding: 0.55rem 0.95rem;
  border-radius: var(--b-r-sm);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: opacity 0.15s ease, background 0.15s ease;
}

.btn-sm {
  padding: 0.42rem 0.75rem;
  font-size: 0.8rem;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-approve {
  background: var(--nf-accent);
  color: #fff;
}

.btn-approve:hover:not(:disabled) {
  background: var(--nf-accent-dark);
}

.btn-reject {
  background: var(--paper);
  color: var(--danger);
  border: 1px solid rgba(220, 38, 38, 0.35);
}

.btn-reject:hover:not(:disabled) {
  background: var(--danger-bg);
}

.btn-secondary {
  background: var(--page-bg);
  color: var(--ink);
  border: 1px solid var(--line);
}

.btn-secondary:hover:not(:disabled) {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--line);
  border-radius: var(--b-r-md);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.86rem;
}

.data-table th,
.data-table td {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: top;
}

.data-table th {
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--muted);
}

.data-table tr:last-child td {
  border-bottom: 0;
}

.data-table tr.muted td {
  opacity: 0.6;
}

.data-table small {
  color: var(--muted);
}

.data-table .tag {
  margin-left: 0.35rem;
  padding: 0 6px;
  border: 1px solid var(--line);
  border-radius: var(--b-r-pill);
  font-size: 0.68rem;
}

.data-table .err {
  display: block;
  max-width: 28rem;
  color: var(--danger);
}

.row-actions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
  white-space: nowrap;
}

.source-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid var(--line);
}

.source-form h3 {
  margin: 0;
  font-size: 1rem;
}

.checks {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.86rem;
}

.checks input {
  accent-color: var(--nf-accent);
}

@media (max-width: 560px) {
  .mini-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .panel {
    padding: 1rem;
  }
}
</style>
