<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { adminApi } from '../../lib/adminApi';
import { formatDateTime } from '../../lib/date';
import { isVideoUrl } from '../../lib/media';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';

defineOptions({ layout: NewsfeedLayout });

const pending = ref([]);
const pendingPage = ref(1);
const pendingTotalPages = ref(1);

const approved = ref([]);
const approvedPage = ref(1);
const approvedTotalPages = ref(1);

const stats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });
const displayStats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });

const editingContent = reactive({});
// What the queue handed us, so an edit can still be told apart from the
// original after the admin has paged away from the card it was typed on.
const originalContent = reactive({});
const expandedContent = reactive({});
const overflowingContent = reactive({});
const textareaEls = {};

const loadingPending = ref(true);
const loadingApproved = ref(true);
const approvingId = ref(null);
const approvingAll = ref(false);
const rejectingId = ref(null);
const viewMode = ref('card');

// One review at a time: a bulk pass and a single verdict would fight over the
// same rows, and either one leaves the list it was launched from out of date.
const busyWithQueue = computed(
  () => approvingAll.value || approvingId.value !== null || rejectingId.value !== null,
);

// Threads post straight to the forum with no queue in front of them, so what
// lands here is what readers flagged after the fact.
const reports = ref([]);
const loadingReports = ref(true);
const actingReportId = ref(null);

// Why a post was turned down, kept per card. Nothing in the app reads these
// back yet — they are labels being banked for later. See moderator.md, tier 2.
const rejectionReason = reactive({});

const REJECTION_REASONS = [
  { value: 'bullying', label: 'Bullying or harassment' },
  { value: 'privacy', label: 'Privacy / doxxing' },
  { value: 'hate', label: 'Hate speech' },
  { value: 'sexual', label: 'Sexual content' },
  { value: 'spam', label: 'Spam or selling' },
  { value: 'off_topic', label: 'Off topic' },
  { value: 'other', label: 'Other' },
];

// What the Tier 1 rules found, for the badge on each card. The score decides
// the order the queue arrives in; this only explains it.
const RISK_CATEGORY_LABELS = {
  self_harm: 'Possible crisis',
  contact_details: 'Contact details',
  named_target: 'Names someone',
};

function riskSummary(post) {
  const flags = post.risk_flags;

  if (!flags || post.risk_level === 'none') return null;

  return {
    level: post.risk_level,
    categories: Object.keys(flags).map((key) => RISK_CATEGORY_LABELS[key] ?? key),
    // The matched substrings, so an admin can disagree with the flag without
    // rereading the whole post looking for what tripped it.
    evidence: Object.values(flags).flat().slice(0, 6),
  };
}

// Computed once per queue load rather than four times per card per render.
const pendingRisks = computed(() =>
  Object.fromEntries(pending.value.map((post) => [post.id, riskSummary(post)])),
);

const banner = reactive({ message: '', tone: 'info' });

function showBanner(message, tone = 'info') {
  banner.message = message;
  banner.tone = tone;
}

function animateStats(next) {
  const from = { ...displayStats };
  const start = performance.now();
  const duration = 500;

  function tick(now) {
    const t = Math.min(1, (now - start) / duration);
    const eased = 1 - Math.pow(1 - t, 3);
    for (const key of Object.keys(next)) {
      displayStats[key] = Math.round(from[key] + (next[key] - from[key]) * eased);
    }
    if (t < 1) requestAnimationFrame(tick);
  }

  requestAnimationFrame(tick);
}

async function loadStats() {
  const data = await adminApi.getStats();
  Object.assign(stats, data);
  animateStats(data);
}

async function loadPending(page = pendingPage.value) {
  loadingPending.value = true;

  try {
    const data = await adminApi.getPending(page);
    pending.value = data.items;
    pendingPage.value = data.page;
    pendingTotalPages.value = data.totalPages;

    data.items.forEach((post) => {
      if (!(post.id in editingContent)) editingContent[post.id] = post.content;
      originalContent[post.id] = post.content;
    });

    nextTick(() => {
      data.items.forEach((post) => checkOverflow(post.id));
    });
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    loadingPending.value = false;
  }
}

async function loadApproved(page = approvedPage.value) {
  loadingApproved.value = true;

  try {
    const data = await adminApi.getApproved(page);
    approved.value = data.items;
    approvedPage.value = data.page;
    approvedTotalPages.value = data.totalPages;
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    loadingApproved.value = false;
  }
}

async function refreshAfterAction({ backToPrevIfEmpty } = {}) {
  await Promise.all([loadStats(), loadPending(), loadApproved()]);

  if (backToPrevIfEmpty && pending.value.length === 0 && pendingPage.value > 1) {
    await loadPending(pendingPage.value - 1);
  }

  if (backToPrevIfEmpty && approved.value.length === 0 && approvedPage.value > 1) {
    await loadApproved(approvedPage.value - 1);
  }
}

async function approve(post) {
  banner.message = '';
  approvingId.value = post.id;

  try {
    const edited = editingContent[post.id] !== post.content ? editingContent[post.id] : undefined;
    await adminApi.approve(post.id, edited);
    await refreshAfterAction({ backToPrevIfEmpty: true });

    showBanner(
      post.image_urls?.length
        ? 'Approved. Copy the text and download the media from the approved list when you are ready to post manually.'
        : 'Approved. Copy the text from the approved list when you are ready to post manually.',
      'success',
    );
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    approvingId.value = null;
  }
}

// Anything the admin retyped and left sitting in a textarea, from any page of
// the queue they have opened, so approving in bulk keeps those edits.
function pendingEdits() {
  const edits = {};

  for (const [id, content] of Object.entries(editingContent)) {
    if (typeof content === 'string' && content.trim() && content !== originalContent[id]) {
      edits[id] = content;
    }
  }

  return edits;
}

async function approveAll() {
  const queued = stats.pending;

  if (queued === 0) {
    showBanner('Nothing pending to approve.', 'info');
    return;
  }

  const label = queued === 1 ? '1 pending submission' : `all ${queued} pending submissions`;
  if (!confirm(`Approve ${label}? They go straight to the posting queue and this cannot be undone.`)) return;

  banner.message = '';
  approvingAll.value = true;

  try {
    const data = await adminApi.approveAll(pendingEdits());

    // The queue is empty now, so there is no page to stay on.
    pendingPage.value = 1;
    approvedPage.value = 1;
    await refreshAfterAction();

    showBanner(
      data.approved === 0
        ? 'The queue was already clear — nothing to approve.'
        : `Approved ${data.approved === 1 ? '1 submission' : `${data.approved} submissions`}. Copy the text from the approved list when you are ready to post.`,
      data.approved === 0 ? 'info' : 'success',
    );
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    approvingAll.value = false;
  }
}

async function reject(post) {
  banner.message = '';
  rejectingId.value = post.id;

  try {
    await adminApi.reject(post.id, rejectionReason[post.id] ?? 'other');
    delete rejectionReason[post.id];
    await refreshAfterAction({ backToPrevIfEmpty: true });
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    rejectingId.value = null;
  }
}

// Everything that leaves here goes straight onto the page, so the copied text
// always carries the page tag plus the submission's reference number — that is
// how a post out on Facebook gets traced back to a row in this queue.
const PAGE_HASHTAG = '#bsufw';

function postReference(post) {
  return String(post.id).padStart(4, '0');
}

function postHashtag(post) {
  return `${PAGE_HASHTAG}${postReference(post)}`;
}

// Posts that already end with their own tag are left alone; ones tagged before
// references existed end with a bare #bsufw, so top those up in place rather
// than leaving two tags behind.
function withPageHashtag(content, post) {
  const tag = postHashtag(post);
  const text = (content || '').trimEnd();

  if (new RegExp(`(^|\\s)${tag}\\s*$`, 'i').test(text)) return text;

  if (new RegExp(`(^|\\s)${PAGE_HASHTAG}\\s*$`, 'i').test(text)) {
    return text.replace(new RegExp(`${PAGE_HASHTAG}\\s*$`, 'i'), tag);
  }

  return text ? `${text}\n\n${tag}` : tag;
}

async function copyPostText(post) {
  try {
    await navigator.clipboard.writeText(withPageHashtag(post.content, post));
    showBanner(`Post text copied with ${postHashtag(post)}.`, 'success');
  } catch (err) {
    showBanner(`Copy failed: ${err.message}`, 'error');
  }
}

function downloadMedia(post) {
  if (!post.image_urls?.length) {
    showBanner('This post does not have media to download.', 'info');
    return;
  }

  post.image_urls.forEach((mediaUrl, index) => {
    const link = document.createElement('a');
    link.href = mediaUrl;
    link.download = `submission-${post.id}-${index + 1}`;
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
  });

  showBanner(
    post.image_urls.length === 1
      ? 'Download started.'
      : `${post.image_urls.length} downloads started.`,
    'success',
  );
}

function truncate(text, length = 90) {
  if (!text) return '';
  return text.length > length ? `${text.slice(0, length).trim()}...` : text;
}

function registerTextarea(id) {
  return (el) => {
    if (el) textareaEls[id] = el;
  };
}

function checkOverflow(id) {
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const el = textareaEls[id];
      if (!el || expandedContent[id]) return;
      overflowingContent[id] = el.scrollHeight > el.clientHeight + 2;
    });
  });
}

function resizeExpanded(id) {
  const el = textareaEls[id];
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${el.scrollHeight}px`;
}

function toggleExpand(post) {
  expandedContent[post.id] = !expandedContent[post.id];

  if (expandedContent[post.id]) {
    nextTick(() => resizeExpanded(post.id));
  } else {
    nextTick(() => checkOverflow(post.id));
  }
}

function onContentInput(post) {
  if (expandedContent[post.id]) {
    resizeExpanded(post.id);
  }
}

async function loadReports() {
  loadingReports.value = true;

  try {
    const data = await adminApi.getThreadReports();
    reports.value = data.items;
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    loadingReports.value = false;
  }
}

// Every action on a report — taking the content down, putting it back, or
// deciding it was fine — closes the report and drops it off the queue.
async function actOnReport(report, action) {
  actingReportId.value = report.id;

  try {
    if (action === 'dismiss') {
      await adminApi.dismissThreadReport(report.id);
      showBanner('Report dismissed.', 'info');
    } else if (report.target === 'reply') {
      await adminApi.hideThreadReply(report.reply.id);
      showBanner('Reply hidden from the thread.', 'info');
    } else {
      await adminApi.hideThread(report.thread.id);
      showBanner('Thread hidden from the forum.', 'info');
    }

    await loadReports();
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    actingReportId.value = null;
  }
}

function reportedText(report) {
  return report.target === 'reply' ? report.reply?.body : report.thread?.title;
}

// Unique visitors. Its own request and its own state: the stat cards above
// animate a fixed set of keys, and these numbers are not moderation work.
const visits = ref(null);

async function loadVisits() {
  try {
    visits.value = await adminApi.getVisits();
  } catch {
    visits.value = null;
  }
}

const visitPeak = computed(() => Math.max(1, ...(visits.value?.days ?? []).map((day) => day.visitors)));

function shortDay(value) {
  const [y, m, d] = value.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString([], { month: 'short', day: 'numeric' });
}

onMounted(() => {
  loadStats();
  loadPending();
  loadApproved();
  loadReports();
  loadVisits();
});
</script>

<template>
  <section class="dashboard">
    <div class="header-row">
      <div>
        <h1>Moderation Queue</h1>
        <p class="subtext">Review submissions before they go live, then post them manually anywhere you want.</p>
      </div>

      <a href="/admin/jobs" class="btn btn-secondary btn-sm">JobRadar queue &rarr;</a>

      <div class="view-toggle" role="group" aria-label="View mode">
        <button type="button" class="view-btn" :class="{ active: viewMode === 'card' }" @click="viewMode = 'card'">
          Cards
        </button>
        <button type="button" class="view-btn" :class="{ active: viewMode === 'table' }" @click="viewMode = 'table'">
          Table
        </button>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card stat-pending">
        <strong>{{ displayStats.pending }}</strong>
        <span class="stat-label">Pending</span>
      </div>
      <div class="stat-card stat-approved">
        <strong>{{ displayStats.approvedToday }}</strong>
        <span class="stat-label">Approved today</span>
      </div>
      <div class="stat-card stat-rejected">
        <strong>{{ displayStats.rejectedToday }}</strong>
        <span class="stat-label">Rejected today</span>
      </div>
    </div>

    <section v-if="visits" class="panel visits">
      <div class="panel-head">
        <h2>Unique visitors</h2>
        <span class="hint visits-note">Each person counted once a day · Philippine time · bots and moderators left out</span>
      </div>

      <dl class="visit-numbers">
        <div><dt>Today</dt><dd>{{ visits.today.toLocaleString() }}</dd></div>
        <div><dt>Yesterday</dt><dd>{{ visits.yesterday.toLocaleString() }}</dd></div>
        <div><dt>Last 7 days</dt><dd>{{ visits.last7.toLocaleString() }}</dd></div>
        <div><dt>Last 30 days</dt><dd>{{ visits.last30.toLocaleString() }}</dd></div>
        <div><dt>All time</dt><dd>{{ visits.total.toLocaleString() }}</dd></div>
      </dl>

      <div class="visit-detail">
        <figure class="visit-chart">
          <figcaption>Daily, last 30 days</figcaption>
          <div class="visit-bars" role="img" :aria-label="`Daily unique visitors over 30 days, peak ${visitPeak}`">
            <span
              v-for="day in visits.days"
              :key="day.date"
              class="visit-bar"
              :style="{ height: `${Math.max(2, (day.visitors / visitPeak) * 100)}%` }"
              :title="`${shortDay(day.date)}: ${day.visitors}`"
            ></span>
          </div>
          <div class="visit-axis">
            <span>{{ shortDay(visits.days[0].date) }}</span>
            <span>Today</span>
          </div>
        </figure>

        <div class="visit-sections">
          <p class="visit-sections-head">Today by section</p>
          <p v-if="!visits.sections.length" class="hint">No visits yet today.</p>
          <ul v-else>
            <li v-for="row in visits.sections" :key="row.section">
              <span>{{ row.label }}</span>
              <b>{{ row.visitors.toLocaleString() }}</b>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <Transition name="banner">
      <p v-if="banner.message" class="banner" :class="banner.tone">{{ banner.message }}</p>
    </Transition>

    <section class="panel">
      <div class="panel-head">
        <h2>Pending submissions</h2>

        <button
          v-if="!loadingPending && pending.length"
          class="btn btn-approve btn-sm"
          :disabled="busyWithQueue"
          @click="approveAll"
        >
          {{ approvingAll ? 'Approving all...' : `Approve all (${stats.pending})` }}
        </button>
      </div>

      <div v-if="loadingPending" class="skeleton-list">
        <div class="skeleton-card" v-for="n in 3" :key="n">
          <div class="skeleton-line skeleton-line--full"></div>
          <div class="skeleton-line skeleton-line--full"></div>
          <div class="skeleton-line skeleton-line--half"></div>
        </div>
      </div>

      <p v-else-if="pending.length === 0" class="hint">No pending submissions.</p>

      <TransitionGroup v-else-if="viewMode === 'card'" name="card" tag="ul" class="queue">
        <li v-for="post in pending" :key="post.id" class="item">
          <div
            v-if="pendingRisks[post.id]"
            class="risk-note"
            :class="`risk-${pendingRisks[post.id].level}`"
          >
            <span class="risk-badge">{{ pendingRisks[post.id].level }} risk</span>
            <span class="risk-cats">{{ pendingRisks[post.id].categories.join(' · ') }}</span>
            <span class="risk-evidence">{{ pendingRisks[post.id].evidence.join('  ·  ') }}</span>
          </div>

          <textarea
            :ref="registerTextarea(post.id)"
            v-model="editingContent[post.id]"
            rows="3"
            class="content-textarea"
            :class="{ expanded: expandedContent[post.id] }"
            @input="onContentInput(post)"
          ></textarea>

          <button
            v-if="overflowingContent[post.id] || expandedContent[post.id]"
            type="button"
            class="see-more"
            @click="toggleExpand(post)"
          >
            {{ expandedContent[post.id] ? 'See less' : 'See more' }}
          </button>

          <div
            v-if="post.image_urls?.length"
            class="image-grid"
            :class="`tiles-${Math.min(post.image_urls.length, 4)}`"
          >
            <template v-for="(imageUrl, index) in post.image_urls" :key="`${post.id}-${index}`">
              <video v-if="isVideoUrl(imageUrl)" :src="imageUrl" controls playsinline preload="metadata" />
              <img v-else :src="imageUrl" alt="" />
            </template>
          </div>

          <div class="meta">Submitted {{ formatDateTime(post.submitted_at) }}</div>

          <div class="actions">
            <button
              class="btn btn-approve"
              :disabled="approvingAll || approvingId === post.id || rejectingId === post.id"
              @click="approve(post)"
            >
              {{ approvingId === post.id ? 'Approving...' : 'Approve' }}
            </button>

            <button
              class="btn btn-reject"
              :disabled="approvingAll || approvingId === post.id || rejectingId === post.id"
              @click="reject(post)"
            >
              {{ rejectingId === post.id ? 'Rejecting...' : 'Reject' }}
            </button>

            <select
              v-model="rejectionReason[post.id]"
              class="reason-select"
              aria-label="Reason for rejecting"
              :disabled="approvingAll || approvingId === post.id || rejectingId === post.id"
            >
              <option :value="undefined" disabled>Reason (optional)</option>
              <option v-for="reason in REJECTION_REASONS" :key="reason.value" :value="reason.value">
                {{ reason.label }}
              </option>
            </select>
          </div>
        </li>
      </TransitionGroup>

      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Submission</th>
              <th>Submitted</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in pending" :key="post.id">
              <td class="cell-content">
                <span class="row-avatar">A</span>
                <span :title="post.content">{{ truncate(post.content) }}</span>
              </td>
              <td class="cell-muted">{{ formatDateTime(post.submitted_at) }}</td>
              <td>
                <span class="status-pill is-pending">Pending</span>
                <span
                  v-if="pendingRisks[post.id]"
                  class="risk-badge risk-badge-inline"
                  :class="`risk-${pendingRisks[post.id].level}`"
                  :title="pendingRisks[post.id].categories.join(' · ')"
                >
                  {{ pendingRisks[post.id].level }}
                </span>
              </td>
              <td class="cell-actions">
                <button
                  class="btn btn-approve btn-sm"
                  :disabled="approvingAll || approvingId === post.id || rejectingId === post.id"
                  @click="approve(post)"
                >
                  {{ approvingId === post.id ? 'Approving...' : 'Approve' }}
                </button>

                <button
                  class="btn btn-reject btn-sm"
                  :disabled="approvingAll || approvingId === post.id || rejectingId === post.id"
                  @click="reject(post)"
                >
                  {{ rejectingId === post.id ? 'Rejecting...' : 'Reject' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" v-if="!loadingPending && pendingTotalPages > 1">
        <button class="page-btn" :disabled="pendingPage <= 1" @click="loadPending(pendingPage - 1)">
          Prev
        </button>
        <span class="page-indicator">Page {{ pendingPage }} of {{ pendingTotalPages }}</span>
        <button class="page-btn" :disabled="pendingPage >= pendingTotalPages" @click="loadPending(pendingPage + 1)">
          Next
        </button>
      </div>
    </section>

    <section class="panel">
      <h2>Recently approved</h2>
      <p class="hint">
        Manual posting queue. Copy the text here — the post's reference tag, like {{ PAGE_HASHTAG }}0001, is
        appended automatically — then upload any downloaded images to Facebook.
      </p>

      <div v-if="loadingApproved" class="skeleton-list">
        <div class="skeleton-card" v-for="n in 2" :key="n">
          <div class="skeleton-line skeleton-line--full"></div>
          <div class="skeleton-line skeleton-line--half"></div>
        </div>
      </div>

      <p v-else-if="approved.length === 0" class="hint">No approved posts yet.</p>

      <TransitionGroup v-else-if="viewMode === 'card'" name="card" tag="ul" class="queue">
        <li v-for="post in approved" :key="post.id" class="item">
          <p class="static-content">{{ post.content }}</p>

          <div
            v-if="post.image_urls?.length"
            class="image-grid"
            :class="`tiles-${Math.min(post.image_urls.length, 4)}`"
          >
            <template v-for="(imageUrl, index) in post.image_urls" :key="`${post.id}-approved-${index}`">
              <video v-if="isVideoUrl(imageUrl)" :src="imageUrl" controls playsinline preload="metadata" />
              <img v-else :src="imageUrl" alt="" />
            </template>
          </div>

          <div class="meta">{{ postHashtag(post) }} · Approved {{ formatDateTime(post.reviewed_at) }}</div>

          <div class="actions">
            <button class="btn btn-approve" @click="copyPostText(post)">Copy text</button>
            <button class="btn btn-secondary" @click="downloadMedia(post)">
              {{ post.image_urls?.length ? 'Download media' : 'No media' }}
            </button>
          </div>
        </li>
      </TransitionGroup>

      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Submission</th>
              <th>Reference</th>
              <th>Approved</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in approved" :key="post.id">
              <td class="cell-content">
                <span class="row-avatar">A</span>
                <span :title="post.content">{{ truncate(post.content) }}</span>
              </td>
              <td class="cell-muted">{{ postHashtag(post) }}</td>
              <td class="cell-muted">{{ formatDateTime(post.reviewed_at) }}</td>
              <td><span class="status-pill is-approved">Approved</span></td>
              <td class="cell-actions">
                <button class="btn btn-approve btn-sm" @click="copyPostText(post)">Copy text</button>
                <button class="btn btn-secondary btn-sm" @click="downloadMedia(post)">
                  {{ post.image_urls?.length ? 'Download media' : 'No media' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" v-if="!loadingApproved && approvedTotalPages > 1">
        <button class="page-btn" :disabled="approvedPage <= 1" @click="loadApproved(approvedPage - 1)">
          Prev
        </button>
        <span class="page-indicator">Page {{ approvedPage }} of {{ approvedTotalPages }}</span>
        <button class="page-btn" :disabled="approvedPage >= approvedTotalPages" @click="loadApproved(approvedPage + 1)">
          Next
        </button>
      </div>
    </section>

    <section class="panel">
      <h2>Reported threads</h2>
      <p class="hint">
        Forum posts go live without review, so this is the takedown queue. Hiding never deletes —
        a hidden thread or reply can be restored from the database if the call was wrong.
      </p>

      <div v-if="loadingReports" class="skeleton-list">
        <div class="skeleton-card" v-for="n in 2" :key="n">
          <div class="skeleton-line skeleton-line--full"></div>
          <div class="skeleton-line skeleton-line--half"></div>
        </div>
      </div>

      <p v-else-if="reports.length === 0" class="hint">Nothing reported. The forum is quiet.</p>

      <ul v-else class="queue">
        <li v-for="report in reports" :key="report.id" class="item">
          <p class="report-meta">
            <span class="report-tag">{{ report.target }}</span>
            <span class="report-reason">{{ report.reason.replace('-', ' ') }}</span>
            <span v-if="report.thread">in &ldquo;{{ report.thread.title }}&rdquo;</span>
            <time :datetime="report.created_at">{{ formatDateTime(report.created_at) }}</time>
          </p>

          <p class="static-content">{{ reportedText(report) }}</p>

          <div class="actions">
            <a
              v-if="report.thread"
              class="btn btn-secondary btn-sm"
              :href="`/threads/${report.thread.id}`"
              target="_blank"
              rel="noopener"
            >
              Open thread
            </a>
            <button
              class="btn btn-reject btn-sm"
              :disabled="actingReportId === report.id"
              @click="actOnReport(report, 'hide')"
            >
              Hide {{ report.target }}
            </button>
            <button
              class="btn btn-approve btn-sm"
              :disabled="actingReportId === report.id"
              @click="actOnReport(report, 'dismiss')"
            >
              Looks fine
            </button>
          </div>
        </li>
      </ul>
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
  color: var(--muted);
}

.view-toggle {
  display: inline-flex;
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

.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 0.9rem;
  margin-bottom: 1.75rem;
}

.stat-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  box-shadow: none;
  padding: 1rem 1.1rem;
}

.stat-card strong {
  display: block;
  font-size: 1.7rem;
  line-height: 1;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.visits-note {
  margin: 0;
  font-size: 0.78rem;
}

.visit-numbers {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.75rem;
  margin: 0 0 1.25rem;
}

.visit-numbers div {
  padding: 0.75rem 0.85rem;
  border: 1px solid var(--line);
  border-radius: var(--b-r-md);
  background: var(--page-bg);
}

.visit-numbers dt {
  font-size: 0.72rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--muted);
}

.visit-numbers dd {
  margin: 0.25rem 0 0;
  font-size: 1.4rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.visit-detail {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  gap: 1.25rem;
}

.visit-chart {
  margin: 0;
}

.visit-chart figcaption,
.visit-sections-head {
  margin: 0 0 0.6rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--muted);
}

.visit-bars {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 7rem;
  padding-bottom: 1px;
  border-bottom: 1px solid var(--line);
}

.visit-bar {
  flex: 1;
  min-width: 0;
  border-radius: 3px 3px 0 0;
  background: var(--nf-accent);
  opacity: 0.85;
}

.visit-bar:last-child {
  opacity: 1;
}

.visit-bar:hover {
  opacity: 1;
  filter: brightness(1.15);
}

.visit-axis {
  display: flex;
  justify-content: space-between;
  margin-top: 0.35rem;
  font-size: 0.72rem;
  color: var(--muted);
}

.visit-sections ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.visit-sections li {
  display: flex;
  justify-content: space-between;
  padding: 0.4rem 0;
  border-bottom: 1px dashed var(--line);
  font-size: 0.86rem;
}

.visit-sections li:last-child {
  border-bottom: 0;
}

.visit-sections b {
  font-variant-numeric: tabular-nums;
}

@media (max-width: 760px) {
  .visit-numbers {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .visit-detail {
    grid-template-columns: minmax(0, 1fr);
  }
}

.stat-label {
  color: var(--muted);
  font-size: 0.82rem;
}

.panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  box-shadow: none;
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
  margin-bottom: 0.75rem;
}

.panel-head h2 {
  margin: 0;
}

.report-meta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.55rem;
  margin: 0 0 0.6rem;
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
  grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
  gap: 0.9rem;
}

.item {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  padding: 1rem;
}

.item textarea {
  width: 100%;
  padding: 0.65rem;
  font: inherit;
  font-size: 0.92rem;
  color: var(--ink);
  background: var(--page-bg);
  border: 1px solid var(--line);
  border-radius: var(--b-r-thumb);
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.item textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
  box-shadow: none;
}

.content-textarea {
  max-height: 4.6rem;
  overflow-y: hidden;
  resize: none;
}

.content-textarea.expanded {
  max-height: none;
}

.see-more {
  display: inline-flex;
  border: none;
  background: none;
  padding: 0.3rem 0 0;
  color: var(--nf-accent);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.static-content {
  white-space: pre-wrap;
  margin: 0 0 0.5rem;
  font-size: 0.92rem;
}

.image-grid {
  display: grid;
  gap: 3px;
  border-radius: var(--b-r-thumb);
  overflow: hidden;
  margin-top: 0.6rem;
}

.image-grid.tiles-1 {
  grid-template-columns: 1fr;
}

.image-grid.tiles-1 img,
.image-grid.tiles-1 video {
  aspect-ratio: 16 / 10;
}

.image-grid.tiles-2 {
  grid-template-columns: 1fr 1fr;
  height: 240px;
}

.image-grid.tiles-3 {
  grid-template-columns: 1.2fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.image-grid.tiles-3 img:first-child,
.image-grid.tiles-3 video:first-child {
  grid-row: 1 / 3;
}

.image-grid.tiles-4 {
  grid-template-columns: 1fr 1fr;
  grid-template-rows: 1fr 1fr;
  height: 320px;
}

.image-grid img,
.image-grid video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.image-grid video {
  background: #000;
}

/* --- Tier 1 risk note ----------------------------------------------------
   Sits above the post, not over it. The rules are advisory: they reorder the
   queue and say why, and the admin reading the card decides. Styled as a note
   rather than an alert on purpose — a red box over every flagged post trains
   people to approve past it. See moderator.md. */
.risk-note {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.4rem 0.6rem;
  margin-bottom: 0.65rem;
  padding: 0.45rem 0.6rem;
  border: 1px solid var(--line);
  border-left: 3px solid var(--muted);
  border-radius: var(--b-r-thumb);
  background: var(--page-bg);
  font-size: 0.76rem;
}

.risk-note.risk-medium {
  border-left-color: var(--info);
}

.risk-note.risk-high {
  border-left-color: var(--danger);
}

.risk-badge {
  font-weight: 700;
  text-transform: capitalize;
  color: var(--ink);
}

.risk-note.risk-high .risk-badge {
  color: var(--danger);
}

.risk-cats {
  color: var(--ink);
}

.risk-badge-inline {
  margin-left: 0.4rem;
  font-size: 0.7rem;
  color: var(--muted);
}

.risk-badge-inline.risk-high {
  color: var(--danger);
}

.risk-evidence {
  flex-basis: 100%;
  font-family: var(--b-mono);
  font-size: 0.72rem;
  color: var(--muted);
  overflow-wrap: anywhere;
}

.reason-select {
  padding: 0.35rem 0.5rem;
  font: inherit;
  font-size: 0.78rem;
  color: var(--muted);
  background: var(--page-bg);
  border: 1px solid var(--line);
  border-radius: var(--b-r-thumb);
}

.reason-select:disabled {
  opacity: 0.5;
}

.meta {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 0.6rem 0;
}

.actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
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
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.1s ease, background 0.15s ease;
}

.btn-sm {
  padding: 0.42rem 0.75rem;
  font-size: 0.8rem;
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn:active:not(:disabled) {
  transform: scale(0.97);
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
  font-size: 0.88rem;
}

.data-table th {
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--muted);
  background: var(--page-bg);
  padding: 0.65rem 0.9rem;
  border-bottom: 1px solid var(--line);
}

.data-table td {
  padding: 0.7rem 0.9rem;
  border-bottom: 1px solid var(--line);
  vertical-align: middle;
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.cell-content {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  max-width: 420px;
}

.cell-muted {
  color: var(--muted);
  white-space: nowrap;
}

.cell-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  white-space: nowrap;
}

.row-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--b-r-pill);
  background: var(--accent-soft);
  color: var(--nf-accent);
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.25rem;
}

.page-btn {
  min-width: 3.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--b-r-pill);
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--ink);
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-indicator {
  font-size: 0.85rem;
  color: var(--muted);
}

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.skeleton-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--b-r-card);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.skeleton-line {
  height: 0.8rem;
  border-radius: var(--b-r-input);
  background: linear-gradient(90deg, var(--nf-surface-2) 25%, var(--nf-line) 37%, var(--nf-surface-2) 63%);
  background-size: 400% 100%;
  animation: shimmer 1.4s ease infinite;
}

.skeleton-line--full {
  width: 100%;
}

.skeleton-line--half {
  width: 45%;
}

.card-enter-active,
.card-leave-active {
  transition: all 0.25s ease;
}

.card-enter-from,
.card-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@keyframes shimmer {
  0% {
    background-position: 100% 50%;
  }

  100% {
    background-position: 0 50%;
  }
}

@media (max-width: 640px) {
  .header-row {
    flex-direction: column;
  }

  .queue {
    grid-template-columns: 1fr;
  }

  .cell-actions {
    white-space: normal;
  }
}
</style>
