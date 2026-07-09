<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';
import TurnstileWidget from '../Components/TurnstileWidget.vue';

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success ?? null);
const fileInput = ref(null);

const form = useForm({
  content: '',
  images: [],
  captchaToken: null,
});

function onFileChange(e) {
  form.images = Array.from(e.target.files || []);
}

function onSubmit() {
  if (!form.content.trim()) {
    form.setError('content', 'Please write something before submitting.');
    return;
  }
  if (form.captchaToken === null) {
    form.setError('captchaToken', 'Please complete the CAPTCHA challenge.');
    return;
  }

  form.post(route('submissions.store'), {
    forceFormData: true,
    onSuccess: () => {
      form.reset();
      if (fileInput.value) fileInput.value.value = '';
    },
  });
}
</script>

<template>
  <section class="submit-page">
    <div class="eyebrow">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path
          d="M21 11.5a8.5 8.5 0 0 1-12.36 7.58L4 20l1.02-4.55A8.5 8.5 0 1 1 21 11.5Z"
          stroke="currentColor"
          stroke-width="1.6"
        />
      </svg>
      <span>Anonymous &middot; BSU Community Wall</span>
    </div>

    <h1 class="headline">Say it,<br /><em>anonymously.</em></h1>

    <p class="subtext">
      Confessions, rants, crushes — whatever's on your mind. Every submission is reviewed by our
      team before it goes live.
    </p>

    <form class="submit-form" @submit.prevent="onSubmit">
      <div class="field">
        <textarea
          v-model="form.content"
          rows="6"
          placeholder="What's on your mind?"
        ></textarea>
      </div>

      <label class="file-field">
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" multiple @change="onFileChange" />
        <span class="file-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l8.49-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.49 8.49a2 2 0 0 1-2.83-2.83l7.78-7.78"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
            />
          </svg>
          {{ form.images.length ? `${form.images.length} image${form.images.length > 1 ? 's' : ''} selected` : 'Attach up to 4 images (optional)' }}
        </span>
      </label>

      <ul v-if="form.images.length" class="file-list">
        <li v-for="image in form.images" :key="`${image.name}-${image.lastModified}`">
          {{ image.name }}
        </li>
      </ul>

      <TurnstileWidget @verified="(token) => (form.captchaToken = token)" />

      <p v-if="form.errors.content" class="banner error">{{ form.errors.content }}</p>
      <p v-else-if="form.errors.captchaToken" class="banner error">{{ form.errors.captchaToken }}</p>
      <p v-else-if="form.errors.images" class="banner error">{{ form.errors.images }}</p>
      <p v-else-if="form.errors['images.0']" class="banner error">{{ form.errors['images.0'] }}</p>
      <p v-if="successMessage" class="banner success">{{ successMessage }}</p>

      <button type="submit" class="submit-btn" :disabled="form.processing">
        <span>{{ form.processing ? 'Submitting…' : 'Submit anonymously' }}</span>
        <span class="btn-icon">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </button>
    </form>
  </section>
</template>

<style scoped>
.submit-page {
  max-width: 560px;
  margin: 0 auto;
  padding: 1rem 0 3rem;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.8rem;
  border: 1px solid var(--line);
  border-radius: 999px;
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  background: #fff;
}

.headline {
  font-family: 'Fraunces', Georgia, serif;
  font-weight: 500;
  font-size: 3rem;
  line-height: 1.05;
  letter-spacing: -0.01em;
  color: var(--ink);
  margin: 1.1rem 0 0.9rem;
}

.headline em {
  font-style: italic;
  font-weight: 400;
  color: var(--accent);
}

.subtext {
  color: var(--muted);
  font-size: 1.02rem;
  line-height: 1.6;
  max-width: 42ch;
  margin: 0 0 2rem;
}

.submit-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 1.5rem;
}

.field {
  position: relative;
}

textarea {
  width: 100%;
  padding: 1rem;
  font: inherit;
  font-size: 0.98rem;
  color: var(--ink);
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  resize: vertical;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(47, 82, 51, 0.12);
}

.file-field input[type='file'] {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
}

.file-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  padding: 0.7rem 1rem;
  font-size: 0.9rem;
  color: var(--ink);
  background: #fff;
  border: 1px dashed var(--line);
  border-radius: 12px;
  cursor: pointer;
  box-sizing: border-box;
}

.file-field:hover .file-btn {
  border-color: var(--accent);
  color: var(--accent);
}

.file-list {
  margin: -0.3rem 0 0;
  padding-left: 1rem;
  color: var(--muted);
  font-size: 0.85rem;
}

.banner {
  margin: 0;
  padding: 0.7rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
}

.error {
  color: #a13a2f;
  background: #fbe9e6;
}

.success {
  color: var(--accent-dark);
  background: #e4ede4;
}

.submit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  align-self: flex-start;
  padding: 0.5rem 0.5rem 0.5rem 1.4rem;
  background: var(--accent);
  color: #fff;
  border: none;
  border-radius: 999px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease, transform 0.1s ease;
}

.submit-btn:hover:not(:disabled) {
  background: var(--accent-dark);
}

.submit-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  background: #fff;
  color: var(--accent-dark);
  border-radius: 50%;
  flex-shrink: 0;
}

@media (max-width: 480px) {
  .headline {
    font-size: 2.3rem;
  }
}
</style>
