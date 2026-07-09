<script setup>
import { onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { adminApi } from '../../lib/adminApi';
import { formatDateTime } from '../../lib/date';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const pending = ref([]);
const pendingPage = ref(1);
const pendingTotalPages = ref(1);

const failedFb = ref([]);
const failedPage = ref(1);
const failedTotalPages = ref(1);

const stats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });
const displayStats = reactive({ pending: 0, approvedToday: 0, rejectedToday: 0 });

const editingContent = reactive({});
const loadingPending = ref(true);
const loadingFailed = ref(true);
const errorMessage = ref('');

const approvingId = ref(null);
const rejectingId = ref(null);
const retryingId = ref(null);

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

function logout() {
  router.post(route('admin.logout'));
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
      <button class="logout" @click="logout">Log out</button>
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

      <TransitionGroup v-else name="card" tag="ul" class="queue">
        <li v-for="post in pending" :key="post.id" class="item">
          <textarea v-model="editingContent[post.id]" rows="3"></textarea>
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

      <TransitionGroup v-else name="card" tag="ul" class="queue">
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
  max-width: 860px;
  margin: 0 auto;
  padding: 0.5rem 0 3rem;
  color: var(--ink);
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

h1 {
  font-family: 'Fraunces', Georgia, serif;
  font-weight: 500;
  font-size: 2rem;
  margin: 0;
}

.subtext {
  color: var(--muted);
  margin: 0.2rem 0 0;
}

.logout {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 999px;
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
  grid-template-columns: repeat(3, 1fr);
  gap: 0.9rem;
  margin-bottom: 1.75rem;
}

.stat-card {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 16px;
  padding: 1rem 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  animation: rise 0.4s ease both;
}

.stat-card strong {
  font-size: 1.7rem;
  line-height: 1;
  font-family: 'Fraunces', Georgia, serif;
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
  background: #fbe6d4;
  color: #92531a;
}

.stat-approved .stat-icon {
  background: #e4ede4;
  color: var(--accent-dark);
}

.stat-rejected .stat-icon {
  background: var(--danger-bg);
  color: var(--danger);
}

.panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.panel h2 {
  font-family: 'Fraunces', Georgia, serif;
  font-weight: 500;
  font-size: 1.3rem;
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
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.item {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1rem;
}

.item textarea {
  width: 100%;
  padding: 0.65rem;
  font: inherit;
  font-size: 0.92rem;
  border: 1px solid var(--line);
  border-radius: 10px;
  resize: vertical;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.item textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(47, 82, 51, 0.12);
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
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
  border-radius: 999px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease, opacity 0.15s ease;
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
  background: #fff;
  color: var(--danger);
  border: 1px solid #f0d3ce;
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
  background: #fff;
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

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.skeleton-card {
  background: #fff;
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
  background: linear-gradient(90deg, #ece9e1 25%, #f5f3ee 37%, #ece9e1 63%);
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
