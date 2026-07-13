<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import { adminApi } from '../../lib/adminApi';
import { formatDateTime } from '../../lib/date';
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
const expandedContent = reactive({});
const overflowingContent = reactive({});
const textareaEls = {};

const loadingPending = ref(true);
const loadingApproved = ref(true);
const approvingId = ref(null);
const rejectingId = ref(null);
const viewMode = ref('card');

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
        ? 'Approved. Copy the text and download the images from the approved list when you are ready to post manually.'
        : 'Approved. Copy the text from the approved list when you are ready to post manually.',
      'success',
    );
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    approvingId.value = null;
  }
}

async function reject(post) {
  banner.message = '';
  rejectingId.value = post.id;

  try {
    await adminApi.reject(post.id);
    await refreshAfterAction({ backToPrevIfEmpty: true });
  } catch (err) {
    showBanner(err.message, 'error');
  } finally {
    rejectingId.value = null;
  }
}

async function copyPostText(post) {
  try {
    await navigator.clipboard.writeText(post.content || '');
    showBanner('Post text copied to your clipboard.', 'success');
  } catch (err) {
    showBanner(`Copy failed: ${err.message}`, 'error');
  }
}

function downloadImages(post) {
  if (!post.image_urls?.length) {
    showBanner('This post does not have images to download.', 'info');
    return;
  }

  post.image_urls.forEach((imageUrl, index) => {
    const link = document.createElement('a');
    link.href = imageUrl;
    link.download = `submission-${post.id}-${index + 1}`;
    link.target = '_blank';
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
  });

  showBanner(
    post.image_urls.length === 1
      ? 'Image download started.'
      : `${post.image_urls.length} image downloads started.`,
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

onMounted(() => {
  loadStats();
  loadPending();
  loadApproved();
});
</script>

<template>
  <section class="dashboard">
    <div class="header-row">
      <div>
        <h1>Moderation Queue</h1>
        <p class="subtext">Review submissions before they go live, then post them manually anywhere you want.</p>
      </div>

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

    <Transition name="banner">
      <p v-if="banner.message" class="banner" :class="banner.tone">{{ banner.message }}</p>
    </Transition>

    <section class="panel">
      <h2>Pending submissions</h2>

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

          <div v-if="post.image_urls?.length" class="image-grid">
            <img v-for="(imageUrl, index) in post.image_urls" :key="`${post.id}-${index}`" :src="imageUrl" alt="" />
          </div>

          <div class="meta">Submitted {{ formatDateTime(post.submitted_at) }}</div>

          <div class="actions">
            <button
              class="btn btn-approve"
              :disabled="approvingId === post.id || rejectingId === post.id"
              @click="approve(post)"
            >
              {{ approvingId === post.id ? 'Approving...' : 'Approve' }}
            </button>

            <button
              class="btn btn-reject"
              :disabled="approvingId === post.id || rejectingId === post.id"
              @click="reject(post)"
            >
              {{ rejectingId === post.id ? 'Rejecting...' : 'Reject' }}
            </button>
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
              <td><span class="status-pill is-pending">Pending</span></td>
              <td class="cell-actions">
                <button
                  class="btn btn-approve btn-sm"
                  :disabled="approvingId === post.id || rejectingId === post.id"
                  @click="approve(post)"
                >
                  {{ approvingId === post.id ? 'Approving...' : 'Approve' }}
                </button>

                <button
                  class="btn btn-reject btn-sm"
                  :disabled="approvingId === post.id || rejectingId === post.id"
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
      <p class="hint">Manual posting queue. Copy the text here, then upload any downloaded images to Facebook.</p>

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

          <div v-if="post.image_urls?.length" class="image-grid">
            <img v-for="(imageUrl, index) in post.image_urls" :key="`${post.id}-approved-${index}`" :src="imageUrl" alt="" />
          </div>

          <div class="meta">Approved {{ formatDateTime(post.reviewed_at) }}</div>

          <div class="actions">
            <button class="btn btn-approve" @click="copyPostText(post)">Copy text</button>
            <button class="btn btn-secondary" @click="downloadImages(post)">
              {{ post.image_urls?.length ? 'Download images' : 'No images' }}
            </button>
          </div>
        </li>
      </TransitionGroup>

      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Submission</th>
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
              <td class="cell-muted">{{ formatDateTime(post.reviewed_at) }}</td>
              <td><span class="status-pill is-approved">Approved</span></td>
              <td class="cell-actions">
                <button class="btn btn-approve btn-sm" @click="copyPostText(post)">Copy text</button>
                <button class="btn btn-secondary btn-sm" @click="downloadImages(post)">
                  {{ post.image_urls?.length ? 'Download images' : 'No images' }}
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
  border-radius: 10px;
}

.view-btn {
  border: none;
  background: transparent;
  padding: 0.45rem 0.8rem;
  border-radius: 8px;
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
  border-radius: 14px;
  box-shadow: var(--shadow-card);
  padding: 1rem 1.1rem;
}

.stat-card strong {
  display: block;
  font-size: 1.7rem;
  line-height: 1;
  font-weight: 800;
  margin-bottom: 0.35rem;
}

.stat-label {
  color: var(--muted);
  font-size: 0.82rem;
}

.panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 16px;
  box-shadow: var(--shadow-card);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.panel h2 {
  margin: 0 0 0.25rem;
  font-size: 1.15rem;
}

.hint {
  color: var(--muted);
  margin: 0 0 1rem;
}

.banner {
  padding: 0.75rem 1rem;
  border-radius: 12px;
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
  border-radius: 14px;
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
  border-radius: 10px;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.item textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
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
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.5rem;
  margin-top: 0.6rem;
}

.image-grid img {
  width: 100%;
  border-radius: 10px;
  aspect-ratio: 4 / 3;
  object-fit: cover;
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
  border-radius: 8px;
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
  border-radius: 12px;
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
  border-radius: 50%;
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
  border-radius: 999px;
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
  border-radius: 14px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.skeleton-line {
  height: 0.8rem;
  border-radius: 6px;
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
