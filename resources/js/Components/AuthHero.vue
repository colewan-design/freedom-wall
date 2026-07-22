<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const SLIDES = [
  {
    image: '/images/auth/hero-1.png',
    heading: 'Connect Anonymously,\nBelong Openly',
  },
  {
    image: '/images/auth/hero-2.png',
    heading: 'One Campus,\nOne Community',
  },
  {
    image: '/images/auth/hero-3.png',
    heading: 'Your Privacy,\nOur Priority',
  },
];

const activeIndex = ref(0);
let timer = null;

function goTo(index) {
  activeIndex.value = index;
}

function startAutoplay() {
  timer = window.setInterval(() => {
    activeIndex.value = (activeIndex.value + 1) % SLIDES.length;
  }, 5000);
}

onMounted(startAutoplay);
onBeforeUnmount(() => {
  if (timer) window.clearInterval(timer);
});
</script>

<template>
  <aside class="auth-hero">
    <div class="hero-slides">
      <img
        v-for="(slide, index) in SLIDES"
        :key="slide.image"
        :src="slide.image"
        alt=""
        class="hero-slide-image"
        :class="{ active: index === activeIndex }"
      />
      <div class="hero-overlay"></div>
    </div>

    <div class="hero-top">
      <span class="hero-brand">
        <img src="/images/branding/bsufw-mark-64.png" alt="" class="hero-brand-mark" />
        BSU FW
      </span>
      <Link href="/wall" class="hero-back">
        Back to website
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </Link>
    </div>

    <div class="hero-copy">
      <h1><template v-for="(line, i) in SLIDES[activeIndex].heading.split('\n')" :key="i">{{ line }}<br v-if="i === 0" /></template></h1>
    </div>

    <div class="hero-bottom">
      <div class="hero-dots">
        <button
          v-for="(slide, index) in SLIDES"
          :key="slide.image"
          type="button"
          class="hero-dot"
          :class="{ active: index === activeIndex }"
          :aria-label="`Show slide ${index + 1}`"
          @click="goTo(index)"
        ></button>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.auth-hero {
  flex: 0 0 42%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  margin: 1rem;
  padding: 2rem;
  border-radius: 20px;
  color: #fff;
  position: relative;
  overflow: hidden;
  background: #0b0f12;
}

.hero-slides {
  position: absolute;
  inset: 0;
}

.hero-slide-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0;
  transition: opacity 1.2s ease;
}

.hero-slide-image.active {
  opacity: 1;
}

.hero-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(150deg, rgba(11, 42, 38, 0.75) 0%, rgba(15, 118, 110, 0.55) 55%, rgba(13, 148, 136, 0.45) 100%),
    linear-gradient(0deg, rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.35));
}

.hero-top,
.hero-copy,
.hero-bottom {
  position: relative;
  z-index: 1;
}

.hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.hero-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 800;
  letter-spacing: 0.02em;
}

.hero-brand-mark {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: 6px;
  object-fit: cover;
}

.hero-back {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  background: rgba(255, 255, 255, 0.14);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  text-decoration: none;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.45rem 0.9rem;
  border-radius: 999px;
}

.hero-copy h1 {
  margin: 0;
  font-size: 2.1rem;
  font-weight: 800;
  line-height: 1.25;
  text-shadow: 0 2px 16px rgba(0, 0, 0, 0.35);
}

.hero-dots {
  display: flex;
  gap: 0.4rem;
}

.hero-dot {
  width: 1.5rem;
  height: 0.3rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.3);
  border: none;
  padding: 0;
  cursor: pointer;
  transition: background 0.2s ease;
}

.hero-dot.active {
  background: #fff;
}

@media (max-width: 900px) {
  .auth-hero {
    display: none;
  }
}
</style>
