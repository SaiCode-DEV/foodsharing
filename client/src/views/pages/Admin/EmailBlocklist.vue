<template>
  <div class="email-blocklist-admin card">
    <div class="card-header">
      <h3>{{ $t('email_blocklist.admin.title') }}</h3>
      <b-button
        variant="primary"
        @click="showCreateModal"
      >
        <i class="fas fa-plus" /> {{ $t('email_blocklist.admin.create_entry') }}
      </b-button>
    </div>

    <div class="card-body">
      <b-alert
        v-if="error"
        variant="danger"
        dismissible
        @dismissed="error = null"
      >
        {{ error }}
      </b-alert>

      <b-alert
        v-if="success"
        variant="success"
        dismissible
        @dismissed="success = null"
      >
        {{ success }}
      </b-alert>

      <b-table
        :items="entries"
        :fields="fields"
        :busy="loading"
        striped
        hover
        responsive
      >
        <template #cell(isActive)="data">
          <b-badge :variant="data.value ? 'success' : 'primary'">
            {{ data.value ? $t('yes') : $t('no') }}
          </b-badge>
        </template>

        <template #cell(reason)="data">
          <span
            v-if="data.value"
            class="text-truncate"
            style="max-width: 300px; display: inline-block;"
          >
            {{ data.value }}
          </span>
          <span v-else class="text-muted">—</span>
        </template>

        <template #cell(createdAt)="data">
          {{ $d(new Date(data.value), 'short') }}
        </template>

        <template #cell(actions)="data">
          <b-button
            size="sm"
            variant="outline-primary"
            @click="editEntry(data.item)"
          >
            <i class="fas fa-edit" /> {{ $t('button.edit') }}
          </b-button>
          <b-button
            size="sm"
            variant="outline-danger"
            @click="confirmDelete(data.item)"
          >
            <i class="fas fa-trash" /> {{ $t('button.delete') }}
          </b-button>
        </template>
      </b-table>
    </div>

    <!-- Create/Edit Modal -->
    <b-modal
      v-model="showModal"
      :title="editingEntry ? $t('email_blocklist.admin.edit_entry') : $t('email_blocklist.admin.create_entry')"
      size="lg"
      no-close-on-backdrop
      :ok-disabled="!formValid"
      @ok="handleModalOk"
    >
      <b-form v-if="!loading">
        <b-form-group :label="$t('email_blocklist.admin.email_pattern')" label-for="email">
          <b-form-input
            id="email"
            v-model="formData.email"
            type="text"
            required
            :placeholder="$t('email_blocklist.admin.pattern_placeholder')"
          />
          <b-form-text>
            {{ $t('email_blocklist.admin.pattern_help') }}
          </b-form-text>
        </b-form-group>

        <b-form-group :label="$t('email_blocklist.admin.description')" label-for="reason">
          <b-form-textarea
            id="reason"
            v-model="formData.reason"
            rows="3"
            :placeholder="$t('email_blocklist.admin.description_placeholder')"
          />
        </b-form-group>

        <b-form-group>
          <b-form-checkbox
            v-model="formData.isActive"
          >
            {{ $t('email_blocklist.admin.active') }}
          </b-form-checkbox>
        </b-form-group>
      </b-form>
    </b-modal>

    <!-- Delete Confirmation Modal -->
    <b-modal
      v-model="showDeleteModal"
      :title="$t('email_blocklist.admin.confirm_delete')"
      @ok="deleteEntry"
    >
      <p>{{ $t('email_blocklist.admin.delete_warning', { pattern: deletingEntry?.email }) }}</p>
    </b-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import i18n from '@/helper/i18n'
import { listBlocklistEntries, createBlocklistEntry, updateBlocklistEntry, deleteBlocklistEntry } from '@/api/emailBlocklist'
import { BAlert, BBadge, BButton, BForm, BFormCheckbox, BFormGroup, BFormInput, BFormText, BFormTextarea, BModal, BTable } from 'bootstrap-vue'

const entries = ref([])
const loading = ref(false)
const error = ref(null)
const success = ref(null)
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingEntry = ref(null)
const deletingEntry = ref(null)

const fields = computed(() => [
  { key: 'email', label: i18n('email_blocklist.admin.email_pattern'), sortable: true },
  { key: 'reason', label: i18n('email_blocklist.admin.description') },
  { key: 'isActive', label: i18n('email_blocklist.admin.active'), sortable: true },
  { key: 'createdAt', label: i18n('email_blocklist.admin.created_at'), sortable: true },
  { key: 'actions', label: i18n('email_blocklist.admin.actions') },
])

const formValid = computed(() => {
  return formData.value.email && formData.value.email.trim().length > 0
})

function getEmptyFormData () {
  return {
    email: '',
    reason: '',
    isActive: true,
  }
}

const formData = ref(getEmptyFormData())

async function loadEntries () {
  loading.value = true
  try {
    entries.value = await listBlocklistEntries()
  } catch (err) {
    error.value = i18n('email_blocklist.admin.load_error')
    console.error(err)
  } finally {
    loading.value = false
  }
}

function showCreateModal () {
  editingEntry.value = null
  formData.value = getEmptyFormData()
  showModal.value = true
}

function editEntry (entry) {
  editingEntry.value = entry
  formData.value = {
    email: entry.email,
    reason: entry.reason || '',
    isActive: Boolean(entry.isActive),
  }
  showModal.value = true
}

async function saveEntry (evt) {
  evt.preventDefault()
  loading.value = true
  try {
    const payload = { ...formData.value }
    if (payload.reason === '') {
      payload.reason = null
    }

    if (editingEntry.value) {
      // Update existing entry
      await updateBlocklistEntry(editingEntry.value.id, payload)
      success.value = i18n('email_blocklist.admin.update_success')
    } else {
      // Create new entry
      await createBlocklistEntry(payload)
      success.value = i18n('email_blocklist.admin.create_success')
    }
    showModal.value = false
    await loadEntries()
  } catch (err) {
    error.value = err.message || i18n('email_blocklist.admin.save_error')
    console.error(err)
  } finally {
    loading.value = false
  }
}

function handleModalOk (evt) {
  saveEntry(evt)
}

function confirmDelete (entry) {
  deletingEntry.value = entry
  showDeleteModal.value = true
}

async function deleteEntry () {
  try {
    await deleteBlocklistEntry(deletingEntry.value.id)
    success.value = i18n('email_blocklist.admin.delete_success')
    await loadEntries()
  } catch (err) {
    error.value = i18n('email_blocklist.admin.delete_error')
    console.error(err)
  } finally {
    deletingEntry.value = null
  }
}

onMounted(loadEntries)
</script>

<style scoped>
.email-blocklist-admin .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
