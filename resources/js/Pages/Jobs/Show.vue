<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';
import JobCard from '../../Components/JobCard.vue';
import { timeAgo } from '../../lib/date';
import { deadlineLabel, formatDay, hostOf, REPORT_REASONS, SECTOR_LABELS, TYPE_LABELS, whereLabel } from '../../lib/jobs';

const props = defineProps({
  listing: { type: Object, required: true },
  save: { type: Object, default: null },
  canTrack: { type: Boolean, default: false },
  statuses: { type: Object, required: true },
  alreadyReported: { type: Boolean, default: false },
  similar: { type: Array, default: () => [] },
});

// The layout's search box searches the board, so typing here takes you there.
const search = ref('');
let typing = null;

function searchBoard(term) {
  search.value = term;
  clearTimeout(typing);
  typing = setTimeout(() => router.get('/jobs', term.trim() ? { q: term.trim() } : {}), 500);
}

const closed = computed(() => props.listing.status === 'closed');

const MISSING_LABELS = {
  employer: 'Employer',
  location: 'Location',
  salary: 'Salary',
  employment_type: 'Employment type',
  deadline: 'Application deadline',
  description: 'Job description',
};

const facts = computed(() => [
  { label: 'Employer', value: props.listing.employer },
  { label: 'Location', value: props.listing.location ?? (props.listing.is_remote ? 'Remote' : null) },
  { label: 'Type', value: TYPE_LABELS[props.listing.employment_type] ?? null },
  { label: 'Sector', value: SECTOR_LABELS[props.listing.sector] ?? null },
  { label: 'Salary', value: props.listing.salary_text },
  { label: 'Deadline', value: props.listing.deadline ? formatDay(props.listing.deadline) : null },
  { label: 'Posted', value: props.listing.published_at ? formatDay(props.listing.published_at) : null },
  { label: 'Found', value: formatDay(props.listing.discovered_at) },
]);

// --- Tracker ---------------------------------------------------------

const tracker = useForm({
  status: props.save?.status ?? 'interested',
  notes: props.save?.notes ?? '',
  applied_on: props.save?.applied_on ?? '',
  follow_up_on: props.save?.follow_up_on ?? '',
});

const savedNote = ref('');

function saveJob() {
  router.post(`/jobs/${props.listing.id}/save`, {}, { preserveScroll: true });
}

function unsaveJob() {
  router.delete(`/jobs/${props.listing.id}/save`, { preserveScroll: true });
}

function updateTracker() {
  tracker
    .transform((data) => ({
      ...data,
      applied_on: data.applied_on || null,
      follow_up_on: data.follow_up_on || null,
    }))
    .patch(`/job-saves/${props.save.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        savedNote.value = 'Saved.';
        setTimeout(() => (savedNote.value = ''), 2500);
      },
    });
}

// --- Report ----------------------------------------------------------

const reported = ref(props.alreadyReported);
const reporting = ref(false);
const reportReason = ref('scam');
const reportError = ref('');

async function sendReport() {
  reportError.value = '';

  try {
    await window.axios.post(`/jobs/${props.listing.id}/report`, { reason: reportReason.value });
    reported.value = true;
    reporting.value = false;
  } catch {
    reportError.value = 'That report did not go through. Try again in a minute.';
  }
}
</script>

<template>
  <Head :title="listing.title" />

  <DirectoryLayout :search="search" search-placeholder="Search jobs" @update:search="searchBoard">
    <div class="job-page">
      <Link href="/jobs" class="back">&larr; All jobs</Link>

      <article class="detail">
        <p v-if="closed" class="banner is-closed">
          This listing is closed — its deadline passed or it disappeared from every source. It stays here for anyone
          who saved it.
        </p>

        <p class="meta">
          <b>{{ listing.employer || 'Employer not stated' }}</b>
          <span class="dot">•</span>
          <span>{{ whereLabel(listing) }}</span>
          <span class="dot">•</span>
          <span>Found {{ timeAgo(listing.discovered_at).toLowerCase() }}</span>
        </p>
        <h1>{{ listing.title }}</h1>

        <div v-if="listing.warnings.length" class="banner is-warn" role="note">
          <strong>Check this one before applying.</strong>
          {{ listing.warnings.join(' · ') }}. These are signs worth a closer look, not proof the job is fake.
          Never pay to apply.
        </div>

        <div class="detail-actions">
          <a
            v-if="!closed"
            :href="listing.apply_url"
            target="_blank"
            rel="noopener noreferrer nofollow"
            class="btn btn-green"
          >
            Open the original post <span aria-hidden="true">&nearr;</span>
          </a>
          <template v-if="canTrack">
            <button v-if="!save" type="button" class="btn btn-plain" @click="saveJob">Save job</button>
            <button v-else type="button" class="btn btn-plain" @click="unsaveJob">Saved · remove</button>
          </template>
          <Link v-else href="/login" class="btn btn-plain">Log in to save</Link>
          <span v-if="deadlineLabel(listing.deadline)" class="deadline">{{ deadlineLabel(listing.deadline) }}</span>
        </div>

        <dl class="facts">
          <div v-for="fact in facts" :key="fact.label">
            <dt>{{ fact.label }}</dt>
            <dd :class="{ 'is-missing': !fact.value }">{{ fact.value || 'Not stated' }}</dd>
          </div>
        </dl>

        <section class="description">
          <h2>About the job</h2>
          <p v-if="listing.description" class="prose">{{ listing.description }}</p>
          <p v-else class="is-missing">
            The source did not include a description. Open the original post for qualifications and how to apply.
          </p>
        </section>

        <p v-if="listing.missing.length" class="missing-note">
          Not in the posting: {{ listing.missing.map((key) => MISSING_LABELS[key]).join(', ') }}. We leave these
          blank rather than guess.
        </p>
      </article>

      <section v-if="similar.length" class="similar">
        <div class="section-head">
          <h2>Similar jobs</h2>
        </div>
        <ul class="similar-list">
          <li v-for="item in similar" :key="item.id">
            <JobCard :listing="item" compact />
          </li>
        </ul>
      </section>
    </div>

    <template #rail>
      <section v-if="canTrack && save" class="rail-card" aria-labelledby="tracker-head">
        <div class="rail-head">
          <h2 id="tracker-head">Your application</h2>
          <Link href="/jobs/mine#saved" class="rail-link">All saved</Link>
        </div>

        <form class="tracker" @submit.prevent="updateTracker">
          <label class="field">
            <span>Status</span>
            <select v-model="tracker.status">
              <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
            </select>
          </label>
          <div class="tracker-row">
            <label class="field">
              <span>Applied on</span>
              <input v-model="tracker.applied_on" type="date" />
            </label>
            <label class="field">
              <span>Follow up</span>
              <input v-model="tracker.follow_up_on" type="date" />
            </label>
          </div>
          <label class="field">
            <span>Notes</span>
            <textarea v-model="tracker.notes" rows="3" maxlength="2000" placeholder="Requirements sent, who you talked to…"></textarea>
          </label>
          <div class="tracker-foot">
            <span class="saved-note" role="status">{{ savedNote }}</span>
            <button type="submit" class="btn btn-green btn-sm" :disabled="tracker.processing">Save</button>
          </div>
        </form>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Where this was posted</h2>
        </div>
        <ul class="sources">
          <li v-for="source in listing.sources" :key="source.id">
            <a :href="source.url" target="_blank" rel="noopener noreferrer nofollow">
              <b>{{ source.label }}</b>
              <small>{{ hostOf(source.url) }} · seen {{ timeAgo(source.first_seen_at).toLowerCase() }}</small>
            </a>
          </li>
        </ul>
        <p v-if="listing.sources.length > 1" class="rail-note">
          The same opening showed up in {{ listing.sources.length }} places, so it is listed once.
        </p>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Something off?</h2>
        </div>
        <p v-if="reported" class="rail-note">Thanks — a moderator will take another look.</p>
        <template v-else-if="reporting">
          <label class="field">
            <span>What's wrong</span>
            <select v-model="reportReason">
              <option v-for="reason in REPORT_REASONS" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
            </select>
          </label>
          <p v-if="reportError" class="error">{{ reportError }}</p>
          <div class="tracker-foot">
            <button type="button" class="btn btn-plain btn-sm" @click="reporting = false">Cancel</button>
            <button type="button" class="btn btn-green btn-sm" @click="sendReport">Send report</button>
          </div>
        </template>
        <template v-else>
          <p class="rail-note">If this looks like a scam, is already filled, or the details are wrong, tell us.</p>
          <button type="button" class="btn btn-plain btn-block" @click="reporting = true">Report this listing</button>
        </template>
      </section>
    </template>
  </DirectoryLayout>
</template>

<style scoped>
.job-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: jobpage / inline-size;
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
  padding: 0.5rem 0.85rem;
  font-size: 0.82rem;
}

.btn-block {
  width: 100%;
  margin-top: 0.9rem;
}

.back {
  align-self: flex-start;
  font-size: 0.84rem;
  font-weight: 650;
  color: var(--d-muted);
  text-decoration: none;
}

.back:hover {
  color: var(--d-green-text);
}

.detail {
  padding: 1.4rem 1.5rem 1.3rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

.meta {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
  margin: 0;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.meta b {
  color: var(--d-green-text);
}

.dot {
  color: var(--d-faint);
}

.detail h1 {
  margin: 0.45rem 0 0;
  font-size: 1.9rem;
  line-height: 1.15;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--d-ink);
}

.banner {
  margin: 0 0 1rem;
  padding: 0.7rem 0.85rem;
  border-radius: var(--d-r-md);
  font-size: 0.84rem;
  line-height: 1.5;
}

.banner.is-closed {
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  color: var(--d-muted);
}

.banner.is-warn {
  margin: 1rem 0 0;
  background: color-mix(in srgb, #c53030 9%, transparent);
  color: #c53030;
}

:global(html[data-theme='dark']) .banner.is-warn {
  color: #f08a8a;
}

.detail-actions {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
  margin-top: 1.1rem;
}

.deadline {
  margin-left: auto;
  font-size: 0.82rem;
  font-weight: 650;
  color: var(--d-muted);
}

.facts {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.1rem 1rem;
  margin: 1.3rem 0 0;
  padding: 0.9rem 0 0;
  border-top: 1px solid var(--d-line);
}

.facts div {
  min-width: 0;
  padding: 0.45rem 0;
}

.facts dt {
  font-size: 0.64rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-faint);
}

.facts dd {
  margin: 0.2rem 0 0;
  overflow-wrap: anywhere;
  font-size: 0.86rem;
  font-weight: 600;
  color: var(--d-ink);
}

.is-missing {
  color: var(--d-faint) !important;
  font-weight: 500 !important;
  font-style: italic;
}

.description {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--d-line);
}

.description h2,
.section-head h2 {
  margin: 0 0 0.5rem;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--d-ink);
}

.prose {
  margin: 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--d-ink-2);
}

.missing-note {
  margin: 1rem 0 0;
  font-size: 0.78rem;
  color: var(--d-faint);
}

.similar-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.similar-list > li {
  min-width: 0;
}

/* --- Rail ----------------------------------------------------------------- */

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

.rail-head h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-link {
  font-size: 0.78rem;
  font-weight: 650;
  color: var(--d-green-text);
  text-decoration: none;
}

.rail-note {
  margin: 0.5rem 0 0;
  font-size: 0.8rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.sources {
  margin: 0;
  padding: 0;
  list-style: none;
}

.sources a {
  display: flex;
  flex-direction: column;
  padding: 0.45rem;
  margin: 0 -0.45rem;
  border-radius: var(--d-r-md);
  text-decoration: none;
  transition: background 0.15s ease;
}

.sources a:hover {
  background: var(--d-soft);
}

.sources b {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.82rem;
  font-weight: 650;
  color: var(--d-ink);
}

.sources small {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.7rem;
  color: var(--d-muted);
}

.tracker {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.tracker-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem;
}

.tracker-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.6rem;
}

.saved-note {
  margin-right: auto;
  font-size: 0.78rem;
  color: var(--d-green-text);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
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
  padding: 0.5rem 0.65rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-md);
  background: var(--d-soft);
  color: var(--d-ink);
  font: inherit;
  font-size: 0.84rem;
}

.field textarea {
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--d-green);
}

.error {
  margin: 0.4rem 0 0;
  font-size: 0.76rem;
  color: #dc2626;
}

@container jobpage (max-width: 620px) {
  .facts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .similar-list {
    grid-template-columns: minmax(0, 1fr);
  }

  .detail {
    padding: 1.1rem 1.05rem;
  }

  .detail h1 {
    font-size: 1.5rem;
  }

  .deadline {
    margin-left: 0;
  }
}
</style>
