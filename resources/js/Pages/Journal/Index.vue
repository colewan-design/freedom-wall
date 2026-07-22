<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';
import { formatDateTime } from '../../lib/date';

defineOptions({ layout: NewsfeedLayout });

defineProps({
  entries: Array,
});

const form = useForm({ content: '' });

function addEntry() {
  if (!form.content.trim()) return;
  form.post(route('journal.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
}

const editingId = ref(null);
const editContent = ref('');

function startEdit(entry) {
  editingId.value = entry.id;
  editContent.value = entry.content;
}

function cancelEdit() {
  editingId.value = null;
  editContent.value = '';
}

function saveEdit(entryId) {
  router.patch(
    route('journal.update', entryId),
    { content: editContent.value },
    { preserveScroll: true, onSuccess: cancelEdit },
  );
}

function destroyEntry(entryId) {
  if (!confirm('Delete this journal entry?')) return;
  router.delete(route('journal.destroy', entryId), { preserveScroll: true });
}
</script>

<template>
  <div class="journal-page">
    <section class="panel">
      <h2>Private journal</h2>
      <p class="hint">Only you can see these entries — not friends, not admins.</p>
      <form @submit.prevent="addEntry">
        <textarea v-model="form.content" rows="3" placeholder="Write something for yourself…"></textarea>
        <p v-if="form.errors.content" class="error">{{ form.errors.content }}</p>
        <button type="submit" :disabled="form.processing || !form.content.trim()">
          {{ form.processing ? 'Saving…' : 'Add entry' }}
        </button>
      </form>
    </section>

    <section v-if="entries.length" class="entry-list">
      <article v-for="entry in entries" :key="entry.id" class="entry">
        <template v-if="editingId === entry.id">
          <textarea v-model="editContent" rows="3"></textarea>
          <div class="entry-actions">
            <button type="button" class="btn-primary" @click="saveEdit(entry.id)">Save</button>
            <button type="button" class="btn-secondary" @click="cancelEdit">Cancel</button>
          </div>
        </template>
        <template v-else>
          <p class="entry-content">{{ entry.content }}</p>
          <div class="entry-footer">
            <span class="entry-date">{{ formatDateTime(entry.created_at) }}</span>
            <div class="entry-actions">
              <button type="button" class="btn-secondary" @click="startEdit(entry)">Edit</button>
              <button type="button" class="btn-secondary" @click="destroyEntry(entry.id)">Delete</button>
            </div>
          </div>
        </template>
      </article>
    </section>
    <p v-else class="empty">No entries yet. Write your first one above.</p>
  </div>
</template>

<style scoped>
.journal-page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.panel {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 1.25rem;
}

.panel h2 {
  margin: 0 0 0.3rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--nf-ink);
}

.hint {
  margin: 0 0 1rem;
  font-size: 0.82rem;
  color: var(--nf-muted);
}

form {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

textarea {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border: 1px solid var(--nf-line);
  border-radius: 10px;
  background: var(--nf-bg);
  color: var(--nf-ink);
  font: inherit;
  resize: vertical;
}

textarea:focus {
  outline: none;
  border-color: var(--nf-accent);
}

form button[type='submit'] {
  align-self: flex-start;
  background: var(--nf-accent);
  color: #fff;
  border: none;
  padding: 0.55rem 1.1rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

form button[type='submit']:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error {
  margin: 0;
  color: #f87171;
  font-size: 0.82rem;
}

.entry-list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.entry {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: 12px;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.entry-content {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.5;
  color: var(--nf-ink);
}

.entry-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.entry-date {
  font-size: 0.78rem;
  color: var(--nf-muted);
}

.entry-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-primary {
  background: var(--nf-accent);
  color: #fff;
  border: none;
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-secondary {
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  border: 1px solid var(--nf-line);
  padding: 0.4rem 0.85rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
}

.empty {
  color: var(--nf-muted);
  font-size: 0.88rem;
}
</style>
