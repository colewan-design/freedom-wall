<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { timeAgo } from '../lib/date';
import { deadlineLabel, SECTOR_LABELS, TYPE_LABELS, whereLabel } from '../lib/jobs';

// One listing on the board, in a match list, or under "similar jobs". The
// card links to the listing's own page; applying always happens from there or
// straight on the employer's site, never inside a card.

const props = defineProps({
  listing: { type: Object, required: true },
  saved: { type: Boolean, default: false },
  // Shown when the student is signed in; otherwise the card has no star.
  canSave: { type: Boolean, default: false },
  score: { type: Number, default: null },
  reasons: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-save']);

const initials = computed(() =>
  (props.listing.employer || props.listing.title)
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase(),
);

const deadline = computed(() => deadlineLabel(props.listing.deadline));
</script>

<template>
  <article class="job" :class="{ 'is-compact': compact, 'has-warning': listing.warnings?.length }">
    <div class="job-head">
      <span class="tile" aria-hidden="true">{{ initials }}</span>

      <div class="job-titles">
        <p class="job-meta">
          <b>{{ listing.employer || 'Employer not stated' }}</b>
          <span v-if="listing.sector" class="dot">•</span>
          <span v-if="listing.sector">{{ SECTOR_LABELS[listing.sector] }}</span>
        </p>
        <h3>
          <Link :href="`/jobs/${listing.id}`">{{ listing.title }}</Link>
        </h3>
      </div>

      <span v-if="score !== null" class="job-score" :title="reasons.length ? `Why: ${reasons.join(', ')}` : null">
        {{ score }}%<small>match</small>
      </span>

      <button
        v-if="canSave"
        type="button"
        class="job-save"
        :class="{ on: saved }"
        :aria-pressed="saved"
        :aria-label="saved ? 'Remove from saved jobs' : 'Save this job'"
        @click="emit('toggle-save', listing)"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" :fill="saved ? 'currentColor' : 'none'" aria-hidden="true">
          <path d="M6 3.5h12v17l-6-4.2-6 4.2v-17Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
        </svg>
      </button>
    </div>

    <ul class="job-facts">
      <li>{{ whereLabel(listing) }}</li>
      <li v-if="listing.employment_type">{{ TYPE_LABELS[listing.employment_type] }}</li>
      <li v-if="listing.is_remote && listing.location">Remote</li>
      <li v-if="listing.salary_text" class="is-pay">{{ listing.salary_text }}</li>
    </ul>

    <p v-if="!compact && listing.excerpt" class="job-excerpt">{{ listing.excerpt }}</p>

    <p v-if="reasons.length" class="job-why">Why it matches: {{ reasons.join(', ') }}</p>

    <p v-if="listing.warnings?.length" class="job-warn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3 2 20h20L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
        <path d="M12 10v4.5M12 17.5v.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
      </svg>
      Check before applying: {{ listing.warnings.join(' · ') }}
    </p>

    <div class="job-foot">
      <span>Found {{ timeAgo(listing.discovered_at).toLowerCase() }}</span>
      <span v-if="listing.source_count > 1" class="dot">•</span>
      <span v-if="listing.source_count > 1">{{ listing.source_count }} sources</span>
      <span v-if="deadline" class="job-deadline">{{ deadline }}</span>
    </div>
  </article>
</template>

<style scoped>
.job {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  padding: 1rem 1.05rem 0.85rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s var(--b-ease);
}

.job:hover {
  border-color: var(--d-line-strong);
  box-shadow: var(--d-shadow-lift);
  transform: translateY(-2px);
}

.job.is-compact {
  padding: 0.85rem 0.9rem 0.75rem;
}

.job-head {
  display: flex;
  align-items: flex-start;
  gap: 0.7rem;
}

.tile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2.3rem;
  height: 2.3rem;
  border-radius: var(--d-r-md);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.82rem;
  font-weight: 800;
  line-height: 1;
}

.job-titles {
  flex: 1;
  min-width: 0;
}

.job-meta {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0;
  overflow: hidden;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--d-muted);
  white-space: nowrap;
}

.job-meta b {
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--d-green-text);
}

.dot {
  color: var(--d-faint);
}

.job h3 {
  margin: 0.2rem 0 0;
  font-size: 1.05rem;
  line-height: 1.3;
  font-weight: 750;
  letter-spacing: -0.02em;
}

.is-compact h3 {
  font-size: 0.95rem;
}

.job h3 :deep(a) {
  color: var(--d-ink);
  text-decoration: none;
}

/* The whole card is the target; the title link stretches over it. */
.job {
  position: relative;
}

.job h3 :deep(a)::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
}

.job-save,
.job-score {
  position: relative;
  z-index: 1;
}

.job-score {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  font-size: 1.05rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
  color: var(--d-green-text);
  font-variant-numeric: tabular-nums;
}

.job-score small {
  margin-top: 0.15rem;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--d-muted);
}

.job-save {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-pill);
  background: var(--d-card);
  color: var(--d-muted);
  cursor: pointer;
  transition: color 0.15s ease, border-color 0.15s ease, background 0.15s ease;
}

.job-save:hover {
  color: var(--d-ink);
  border-color: var(--d-line-strong);
}

.job-save.on {
  color: var(--d-green-text);
  border-color: var(--d-green-tint-line);
  background: var(--d-green-tint);
}

.job-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.job-facts li {
  padding: 0.2rem 0.55rem;
  border-radius: var(--d-r-pill);
  background: var(--d-soft);
  border: 1px solid var(--d-line);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--d-ink-2);
}

.job-facts li.is-pay {
  background: var(--d-green-tint);
  border-color: var(--d-green-tint-line);
  color: var(--d-green-text);
}

.job-excerpt {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.55;
  color: var(--d-muted);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.job-why {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--d-ink-2);
}

.job-warn {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
  padding: 0.4rem 0.6rem;
  border-radius: var(--d-r-sm);
  background: color-mix(in srgb, #c53030 9%, transparent);
  font-size: 0.75rem;
  font-weight: 600;
  color: #c53030;
}

:global(html[data-theme='dark']) .job-warn {
  color: #f08a8a;
}

.job-warn svg {
  flex-shrink: 0;
}

.job-foot {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
  font-size: 0.74rem;
  color: var(--d-faint);
}

.job-deadline {
  margin-left: auto;
  font-weight: 650;
  color: var(--d-muted);
}

@media (prefers-reduced-motion: reduce) {
  .job,
  .job:hover {
    transition: none;
    transform: none;
  }
}
</style>
