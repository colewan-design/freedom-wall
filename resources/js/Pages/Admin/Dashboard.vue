<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import { adminApi } from '../../lib/adminApi';
import { formatDateTime } from '../../lib/date';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';

defineOptions({ layout: NewsfeedLayout });

const pending = ref([]);
const pendingPage = ref(1);
const pendingTotalPages = ref(1);

const failedFb = ref([]);
const failedPage = ref(1);
const failedTotalPages = ref(1);

const stats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });
const displayStats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });

const editingContent = reactive({});
const expandedContent = reactive({});
const overflowingContent = reactive({});
const textareaEls = {};
const loadingPending = ref(true);
const loadingFailed = ref(true);
const errorMessage = ref('');

const approvingId = ref(null);
const rejectingId = ref(null);
const retryingId = ref(null);

const viewMode = ref('card');

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
    data.items.forEach((p) => {
      if (!(p.id in editingContent)) editingContent[p.id] = p.content;
    });
    nextTick(() => {
      data.items.forEach((p) => checkOverflow(p.id));
    });
  } catch (err) {
    errorMessage.value = err.message;
  } finally {
    loadingPending.value = false;
  }
}

async function loadFailed(page = failedPage.value) {
  loadingFailed.value = true;
  try {
    const data = await adminApi.getFailedFacebook(page);
    failedFb.value = data.items;
    failedPage.value = data.page;
    failedTotalPages.value = data.totalPages;
  } catch (err) {
    errorMessage.value = err.message;
  } finally {
    loadingFailed.value = false;
  }
}

async function refreshAfterAction({ backToPrevIfEmpty } = {}) {
  await Promise.all([loadStats(), loadPending(), loadFailed()]);
  if (backToPrevIfEmpty && pending.value.length === 0 && pendingPage.value > 1) {
    await loadPending(pendingPage.value - 1);
  }
  if (backToPrevIfEmpty && failedFb.value.length === 0 && failedPage.value > 1) {
    await loadFailed(failedPage.value - 1);
  }
}

async function approve(post) {
  errorMessage.value = '';
  approvingId.value = post.id;
  try {
    const edited = editingContent[post.id] !== post.content ? editingContent[post.id] : undefined;
    const result = await adminApi.approve(post.id, edited);
    if (result.fb_error) {
      errorMessage.value = `Approved, but Facebook post failed: ${result.fb_error}`;
    }
    await refreshAfterAction({ backToPrevIfEmpty: true });
  } catch (err) {
    errorMessage.value = err.message;
  } finally {
    approvingId.value = null;
  }
}

async function reject(post) {
  errorMessage.value = '';
  rejectingId.value = post.id;
  try {
    await adminApi.reject(post.id);
    await refreshAfterAction({ backToPrevIfEmpty: true });
  } catch (err) {
    errorMessage.value = err.message;
  } finally {
    rejectingId.value = null;
  }
}

async function retryFacebook(post) {
  errorMessage.value = '';
  retryingId.value = post.id;
  try {
    await adminApi.retryFacebook(post.id);
    await refreshAfterAction({ backToPrevIfEmpty: true });
  } catch (err) {
    errorMessage.value = `Retry failed: ${err.message}`;
  } finally {
    retryingId.value = null;
  }
}

function truncate(text, length = 90) {
  if (!text) return '';
  return text.length > length ? `${text.slice(0, length).trim()}…` : text;
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
  loadFailed();
});
</script>

<template>
  <section class="dashboard">
    <div class="header-row">
      <div>
        <h1>Moderation Queue</h1>
        <p class="subtext">Review submissions before they go live.</p>
      </div>
      <div class="header-actions">
        <div class="view-toggle" role="group" aria-label="View mode">
          <button
            type="button"
            class="view-btn"
            :class="{ active: viewMode === 'card' }"
            @click="viewMode = 'card'"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8" /><rect x="13" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8" /><rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8" /><rect x="13" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8" /></svg>
            Cards
          </button>
          <button
            type="button"
            class="view-btn"
            :class="{ active: viewMode === 'table' }"
            @click="viewMode = 'table'"
          >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="16" rx="1.5" stroke="currentColor" stroke-width="1.8" /><path d="M3 10h18M9 10v10" stroke="currentColor" stroke-width="1.8" /></svg>
            Table
          </button>
        </div>
      </div>
    </div>

    <div class="stats">
      <div class="stat-card stat-pending">
        <span class="stat-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
          </svg>
        </span>
        <strong>{{ displayStats.pending }}</strong>
        <span class="stat-label">Pending</span>
      </div>
      <div class="stat-card stat-approved">
        <span class="stat-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <strong>{{ displayStats.approvedToday }}</strong>
        <span class="stat-label">Approved today</span>
      </div>
      <div class="stat-card stat-rejected">
        <span class="stat-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </span>
        <strong>{{ displayStats.rejectedToday }}</strong>
        <span class="stat-label">Rejected today</span>
      </div>
    </div>

    <Transition name="banner">
      <p v-if="errorMessage" class="banner error">{{ errorMessage }}</p>
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
              <span class="spinner" v-if="approvingId === post.id"></span>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none">
                <path d="M5 12.5 9.5 17 19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              {{ approvingId === post.id ? 'Approving…' : 'Approve' }}
            </button>
            <button
              class="btn btn-reject"
              :disabled="approvingId === post.id || rejectingId === post.id"
              @click="reject(post)"
            >
              <span class="spinner" v-if="rejectingId === post.id"></span>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none">
                <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              {{ rejectingId === post.id ? 'Rejecting…' : 'Reject' }}
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
                  <span class="spinner" v-if="approvingId === post.id"></span>
                  {{ approvingId === post.id ? 'Approving…' : 'Approve' }}
                </button>
                <button
                  class="btn btn-reject btn-sm"
                  :disabled="approvingId === post.id || rejectingId === post.id"
                  @click="reject(post)"
                >
                  <span class="spinner" v-if="rejectingId === post.id"></span>
                  {{ rejectingId === post.id ? 'Rejecting…' : 'Reject' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" v-if="!loadingPending && pendingTotalPages > 1">
        <button class="page-btn" :disabled="pendingPage <= 1" @click="loadPending(pendingPage - 1)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
        <span class="page-indicator">Page {{ pendingPage }} of {{ pendingTotalPages }}</span>
        <button class="page-btn" :disabled="pendingPage >= pendingTotalPages" @click="loadPending(pendingPage + 1)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
      </div>
    </section>

    <section class="panel">
      <h2>Failed Facebook posts</h2>
      <p class="hint">Approved on the site, but didn't make it to the Facebook Page.</p>

      <div v-if="loadingFailed" class="skeleton-list">
        <div class="skeleton-card" v-for="n in 2" :key="n">
          <div class="skeleton-line skeleton-line--full"></div>
          <div class="skeleton-line skeleton-line--half"></div>
        </div>
      </div>

      <p v-else-if="failedFb.length === 0" class="hint">No failed posts right now.</p>

      <TransitionGroup v-else-if="viewMode === 'card'" name="card" tag="ul" class="queue">
        <li v-for="post in failedFb" :key="post.id" class="item">
          <p class="static-content">{{ post.content }}</p>
          <div v-if="post.image_urls?.length" class="image-grid">
            <img v-for="(imageUrl, index) in post.image_urls" :key="`${post.id}-failed-${index}`" :src="imageUrl" alt="" />
          </div>
          <div class="meta">Approved {{ formatDateTime(post.reviewed_at) }}</div>
          <div class="actions">
            <button class="btn btn-approve" :disabled="retryingId === post.id" @click="retryFacebook(post)">
              <span class="spinner" v-if="retryingId === post.id"></span>
              <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none">
                <path d="M4 4v5h5M20 20v-5h-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5.5 15a7 7 0 1 0 1-8.5L4 9M18.5 9a7 7 0 0 1-1 8.5l2.5 2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              {{ retryingId === post.id ? 'Retrying…' : 'Retry Facebook post' }}
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
            <tr v-for="post in failedFb" :key="post.id">
              <td class="cell-content">
                <span class="row-avatar">A</span>
                <span :title="post.content">{{ truncate(post.content) }}</span>
              </td>
              <td class="cell-muted">{{ formatDateTime(post.reviewed_at) }}</td>
              <td><span class="status-pill is-failed">Failed</span></td>
              <td class="cell-actions">
                <button class="btn btn-approve btn-sm" :disabled="retryingId === post.id" @click="retryFacebook(post)">
                  <span class="spinner" v-if="retryingId === post.id"></span>
                  {{ retryingId === post.id ? 'Retrying…' : 'Retry' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" v-if="!loadingFailed && failedTotalPages > 1">
        <button class="page-btn" :disabled="failedPage <= 1" @click="loadFailed(failedPage - 1)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
        <span class="page-indicator">Page {{ failedPage }} of {{ failedTotalPages }}</span>
        <button class="page-btn" :disabled="failedPage >= failedTotalPages" @click="loadFailed(failedPage + 1)">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
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

  --ink: var(--nf-ink);
  --muted: var(--nf-muted);
  --paper: var(--nf-panel);
  --line: var(--nf-line);
  --page-bg: var(--nf-surface-2);
  --accent-soft: rgba(13, 148, 136, 0.18);
  --danger: #f87171;
  --danger-bg: rgba(220, 38, 38, 0.18);
  --status-pending-fg: #fbbf24;
  --status-pending-bg: rgba(180, 83, 9, 0.22);
  --status-active-fg: #4ade80;
  --status-active-bg: rgba(21, 128, 61, 0.22);
  --status-draft-fg: var(--nf-muted);
  --status-draft-bg: var(--nf-surface-2);
  --status-rejected-fg: #f87171;
  --status-rejected-bg: rgba(185, 28, 28, 0.22);

  color: var(--ink);
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  gap: 1rem;
  flex-wrap: wrap;
}

h1 {
  font-weight: 800;
  font-size: 1.85rem;
  letter-spacing: -0.02em;
  margin: 0;
}

.subtext {
  color: var(--muted);
  margin: 0.2rem 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.view-toggle {
  display: inline-flex;
  padding: 0.2rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 10px;
}

.view-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: none;
  background: transparent;
  padding: 0.4rem 0.75rem;
  border-radius: 7px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}

.view-btn.active {
  background: var(--accent-soft);
  color: var(--accent);
}

.logout {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 0.5rem 1.1rem;
  cursor: pointer;
  font-weight: 600;
  color: var(--ink);
  transition: border-color 0.15s ease, transform 0.1s ease;
}

.logout:hover {
  border-color: var(--accent);
  color: var(--accent);
}

.logout:active {
  transform: scale(0.97);
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
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  animation: rise 0.4s ease both;
}

.stat-card strong {
  font-size: 1.7rem;
  line-height: 1;
  font-weight: 800;
}

.stat-label {
  font-size: 0.8rem;
  color: var(--muted);
}

.stat-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 50%;
  margin-bottom: 0.2rem;
}

.stat-pending .stat-icon {
  background: var(--status-pending-bg);
  color: var(--status-pending-fg);
}

.stat-approved .stat-icon {
  background: var(--status-active-bg);
  color: var(--status-active-fg);
}

.stat-rejected .stat-icon {
  background: var(--danger-bg);
  color: var(--danger);
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
  font-weight: 700;
  font-size: 1.15rem;
  margin: 0 0 0.25rem;
}

.hint {
  color: var(--muted);
  margin: 0 0 1rem;
}

.banner {
  padding: 0.7rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.banner.error {
  color: var(--danger);
  background: var(--danger-bg);
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
  align-items: start;
}

.item {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1rem;
  min-width: 0;
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
  resize: vertical;
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}

.item textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-soft);
}

.content-textarea {
  max-height: 4.6rem;
  overflow-y: hidden;
  resize: none;
}

.content-textarea.expanded {
  max-height: none;
  overflow-y: hidden;
}

.see-more {
  display: inline-flex;
  border: none;
  background: none;
  padding: 0.3rem 0 0;
  margin: 0 0 -0.2rem;
  color: var(--accent);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.see-more:hover {
  color: var(--accent-dark);
  text-decoration: underline;
}

.static-content {
  white-space: pre-wrap;
  margin: 0 0 0.5rem;
  font-size: 0.92rem;
}

.item img {
  width: 100%;
  border-radius: 10px;
  margin-top: 0.6rem;
  aspect-ratio: 4 / 3;
  object-fit: cover;
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 0.5rem;
  margin-top: 0.6rem;
}

.meta {
  font-size: 0.78rem;
  color: var(--muted);
  margin: 0.5rem 0;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: none;
  padding: 0.5rem 0.9rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease, opacity 0.15s ease;
}

.btn-sm {
  padding: 0.4rem 0.7rem;
  font-size: 0.8rem;
}

.btn:active:not(:disabled) {
  transform: scale(0.96);
}

.btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-approve {
  background: var(--accent);
  color: #fff;
}

.btn-approve:hover:not(:disabled) {
  background: var(--accent-dark);
}

.btn-reject {
  background: var(--paper);
  color: var(--danger);
  border: 1px solid rgba(220, 38, 38, 0.35);
}

.btn-reject:hover:not(:disabled) {
  background: var(--danger-bg);
}

.spinner {
  width: 13px;
  height: 13px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  animation: spin 0.6s linear infinite;
}

.btn-reject .spinner {
  border: 2px solid rgba(161, 58, 47, 0.25);
  border-top-color: var(--danger);
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card-enter-active {
  transition: all 0.3s ease;
}

.card-leave-active {
  transition: all 0.25s ease;
  position: absolute;
}

.card-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}

.card-leave-to {
  opacity: 0;
  transform: translateX(24px) scale(0.98);
}

.card-move {
  transition: transform 0.3s ease;
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

.data-table tbody tr:hover {
  background: var(--page-bg);
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
  color: var(--accent);
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 50%;
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.15s ease, transform 0.1s ease;
}

.page-btn:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--accent);
}

.page-btn:active:not(:disabled) {
  transform: scale(0.94);
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-indicator {
  font-size: 0.85rem;
  color: var(--muted);
}

@media (max-width: 640px) {
  .header-row {
    flex-direction: column;
    gap: 0.75rem;
  }

  .queue {
    grid-template-columns: 1fr;
  }

  .actions {
    flex-wrap: wrap;
  }
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

@keyframes shimmer {
  0% {
    background-position: 100% 50%;
  }
  100% {
    background-position: 0 50%;
  }
}
</style>
