<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import DirectoryLayout from '../../Layouts/DirectoryLayout.vue';

// A way to chip in, and nothing more: no tiers, no perks, no list of names.
// The QR is a static InstaPay code, so any amount works and nothing about the
// payment ever touches this site.

const QR = '/images/support/instapay-qr.png';

const search = ref('');
const zoomed = ref(false);

const STEPS = [
  { title: 'Open your e-wallet or bank app', body: 'GCash, Maya, or any bank app with InstaPay QR — most Philippine banks have it.' },
  { title: 'Scan the code', body: 'Use “Scan QR” or “Pay QR”. On a phone? Save the image first, then upload it from your gallery on the same screen.' },
  { title: 'Enter any amount', body: 'Whatever feels right. Even small amounts add up, and nothing is expected.' },
];
</script>

<template>
  <Head title="Support the wall" />

  <DirectoryLayout v-model:search="search" search-placeholder="Search the wall">
    <div class="support">
      <header class="hero">
        <p class="hero-badge">Support</p>
        <h1>Help keep the wall running</h1>
        <p class="hero-lede">
          The wall, the forum, the chat and JobRadar are free and stay that way. If any of them helped you, you can
          chip in for the server and the upkeep. It is never required, and nothing on the site changes either way.
        </p>
      </header>

      <section class="qr-card" aria-labelledby="qr-head">
        <button type="button" class="qr-frame" aria-label="Show the QR code larger" @click="zoomed = true">
          <img :src="QR" alt="InstaPay QR code for supporting BSU Freedom Wall" width="704" height="704" />
        </button>

        <div class="qr-body">
          <h2 id="qr-head">Scan with InstaPay</h2>
          <p class="qr-sub">Works with GCash, Maya and most bank apps in the Philippines.</p>

          <ol class="steps">
            <li v-for="(step, index) in STEPS" :key="step.title">
              <span class="tile" aria-hidden="true">{{ index + 1 }}</span>
              <div>
                <b>{{ step.title }}</b>
                <p>{{ step.body }}</p>
              </div>
            </li>
          </ol>

          <div class="qr-actions">
            <a :href="QR" download="bsu-freedom-wall-instapay-qr.png" class="btn btn-green">
              Save the QR <span aria-hidden="true">&darr;</span>
            </a>
            <button type="button" class="btn btn-plain" @click="zoomed = true">View larger</button>
          </div>
        </div>
      </section>

      <p class="fineprint">
        Check the name your app shows before you confirm, and only use the code on this page. We will never message
        you asking for money, and nobody needs to pay to post, apply for a job, or have a post approved.
      </p>
    </div>

    <template #rail>
      <section class="rail-card">
        <div class="rail-head">
          <h2>Where it goes</h2>
        </div>
        <ul class="rail-list">
          <li>The server the site runs on</li>
          <li>The domain and its upkeep</li>
          <li>Time spent reviewing posts and keeping JobRadar's sources working</li>
        </ul>
      </section>

      <section class="rail-card">
        <div class="rail-head">
          <h2>Other ways to help</h2>
        </div>
        <ul class="rail-list">
          <li><Link href="/jobs">Share a job opening</Link> you came across</li>
          <li>Report posts that break the rules</li>
          <li><Link href="/threads">Suggest an idea</Link> on the forum</li>
        </ul>
      </section>
    </template>
  </DirectoryLayout>

  <Teleport to="body">
    <div v-if="zoomed" class="zoom" role="dialog" aria-label="InstaPay QR code" @click="zoomed = false" @keydown.esc="zoomed = false">
      <img :src="QR" alt="InstaPay QR code for supporting BSU Freedom Wall" />
      <button type="button" class="zoom-close" aria-label="Close" autofocus @click="zoomed = false">&times;</button>
    </div>
  </Teleport>
</template>

<style scoped>
.support {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  container: support / inline-size;
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

.tile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 1.65rem;
  height: 1.65rem;
  border-radius: var(--d-r-sm);
  background: var(--d-green);
  color: var(--d-green-ink);
  font-size: 0.72rem;
  font-weight: 800;
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
  max-width: 52ch;
  font-size: 0.95rem;
  line-height: 1.6;
  color: var(--d-muted);
}

/* --- QR card -------------------------------------------------------------- */

.qr-card {
  display: grid;
  grid-template-columns: minmax(0, 17rem) minmax(0, 1fr);
  gap: 1.6rem;
  align-items: start;
  padding: 1.4rem;
  background: var(--d-card);
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  box-shadow: var(--d-shadow);
}

/* Always white behind the code, in either theme: scanners want dark modules
   on a light field, and the image itself has a white background. */
.qr-frame {
  display: block;
  width: 100%;
  padding: 0.6rem;
  border: 1px solid var(--d-line);
  border-radius: var(--d-r-lg);
  background: #fff;
  cursor: zoom-in;
  transition: box-shadow 0.15s ease, transform 0.15s var(--b-ease);
}

.qr-frame:hover {
  box-shadow: var(--d-shadow-lift);
  transform: translateY(-2px);
}

.qr-frame img {
  display: block;
  width: 100%;
  height: auto;
}

.qr-body h2 {
  margin: 0;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.025em;
  color: var(--d-ink);
}

.qr-sub {
  margin: 0.3rem 0 0;
  font-size: 0.86rem;
  color: var(--d-muted);
}

.steps {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  margin: 1.1rem 0 0;
  padding: 0;
  list-style: none;
}

.steps li {
  display: flex;
  gap: 0.7rem;
}

.steps b {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--d-ink);
}

.steps p {
  margin: 0.15rem 0 0;
  font-size: 0.84rem;
  line-height: 1.5;
  color: var(--d-muted);
}

.qr-actions {
  display: flex;
  gap: 0.55rem;
  flex-wrap: wrap;
  margin-top: 1.25rem;
}

.fineprint {
  margin: 0;
  padding: 0.85rem 1rem;
  border: 1px dashed var(--d-line-strong);
  border-radius: var(--d-r-md);
  font-size: 0.8rem;
  line-height: 1.55;
  color: var(--d-muted);
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
  margin-bottom: 0.6rem;
}

.rail-head h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 750;
  letter-spacing: -0.015em;
  color: var(--d-ink);
}

.rail-list {
  margin: 0;
  padding-left: 1rem;
  font-size: 0.82rem;
  line-height: 1.55;
  color: var(--d-muted);
}

.rail-list li + li {
  margin-top: 0.35rem;
}

.rail-list :deep(a) {
  font-weight: 650;
  color: var(--d-green-text);
  text-decoration: none;
}

/* --- Zoom ----------------------------------------------------------------- */

.zoom {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: var(--d-scrim);
  cursor: zoom-out;
}

.zoom img {
  width: min(88vw, 30rem);
  height: auto;
  padding: 0.8rem;
  border-radius: var(--d-r-xl);
  background: #fff;
  box-shadow: var(--d-shadow-lift);
}

.zoom-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 2.4rem;
  height: 2.4rem;
  border: 0;
  border-radius: var(--d-r-pill);
  background: #fff;
  color: #10141a;
  font-size: 1.4rem;
  line-height: 1;
  cursor: pointer;
}

/* --- Responsive ----------------------------------------------------------- */

@container support (max-width: 620px) {
  .qr-card {
    grid-template-columns: minmax(0, 1fr);
  }

  .qr-frame {
    max-width: 18rem;
    margin: 0 auto;
  }

  .hero h1 {
    font-size: 1.9rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .qr-frame,
  .qr-frame:hover {
    transition: none;
    transform: none;
  }
}
</style>
