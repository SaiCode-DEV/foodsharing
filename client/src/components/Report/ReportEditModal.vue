<template>
  <b-modal
    :visible="show"
    :title="$t('reports.edit_report')"
    size="lg"
    @hidden="$emit('close')"
  >
    <b-alert
      v-if="error"
      variant="danger"
      dismissible
      @dismissed="error = null"
    >
      {{ error }}
    </b-alert>
    <b-form>
      <!-- Editable Fields -->
      <b-form-group :label="$t('reports.status')" label-for="status">
        <vSelect
          v-model="formData.status"
          taggable
          label="text"
          :selectable="option => option.selectable !== false"
          :clearable="false"
          :options="statusSuggestList"
        />
      </b-form-group>

      <b-form-group :label="$t('reports.consequence')" label-for="consequence">
        <vSelect
          v-model="formData.consequence"
          taggable
          label="text"
          :options="consequenceSuggestList"
        />
      </b-form-group>

      <!-- Reminder Date and Time (separate inputs for browser compatibility) -->
      <b-form-group :label="$t('reports.reminder_at')" label-for="reminder-date">
        <div class="d-flex">
          <b-form-input
            id="reminder-date"
            v-model="formData.reminderDate"
            type="date"
            :placeholder="$t('reports.set_reminder')"
            class="mr-2"
          />
          <b-form-input
            id="reminder-time"
            v-model="formData.reminderTime"
            type="time"
            :placeholder="$t('reports.set_reminder')"
          />
        </div>
      </b-form-group>

      <!-- Forum Thread Management -->
      <b-form-group :label="$t('reports.forum_thread')" label-for="forum-thread">
        <div class="d-flex align-items-center">
          <b-form-input
            id="forum-thread"
            v-model.number="formData.forumThreadId"
            type="number"
            :placeholder="$t('reports.enter_forum_thread_id')"
            class="mr-2"
          />
          <b-button
            v-if="formData.forumThreadId"
            variant="outline-secondary"
            size="sm"
            :href="urls.forumThread(props.regionId, formData.forumThreadId)"
            target="_blank"
          >
            <i class="fas fa-external-link-alt" /> {{ $t('reports.view') }}
          </b-button>
          <b-button
            v-if="formData.forumThreadId"
            variant="outline-danger"
            size="sm"
            class="ml-2"
            @click="formData.forumThreadId = null"
          >
            <i class="fas fa-times" /> {{ $t('reports.remove') }}
          </b-button>
        </div>
        <small v-if="formData.forumThreadId" class="form-text text-muted">
          {{ $t('reports.forum_thread_linked') }}
        </small>
      </b-form-group>
    </b-form>

    <template #modal-footer>
      <b-button
        variant="secondary"
        @click="$emit('close')"
      >
        {{ $t('button.cancel') }}
      </b-button>
      <b-button
        v-if="mayDelete"
        variant="danger"
        class="mr-auto"
        @click="doDelete"
      >
        {{ $t('button.delete') }}
      </b-button>
      <b-button
        variant="primary"
        :disabled="loading"
        @click="saveChanges"
      >
        <b-spinner
          v-if="loading"
          small
          class="mr-2"
        />
        {{ $t('button.save') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script setup>
import { reactive, watch, ref, getCurrentInstance } from 'vue'
import { updateReport } from '@/api/report'
import { urls } from '@/helper/urls'
import { pulseError } from '@/script'
import vSelect from 'vue-select'
import 'css/vue-select.css'

const props = defineProps({
  regionId: { type: Number, required: true },
  report: { type: Object, required: true },
  show: { type: Boolean, default: false },
  mayDelete: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'close', 'delete'])

const { proxy } = getCurrentInstance()

const error = ref(null)
const loading = ref(false)
const formData = reactive({
  status: null,
  consequence: null,
  forumThreadId: null,
  reminderDate: null,
  reminderTime: null,
})

const statusOptions = [
  { value: 'reports.statuses.to_do', text: proxy.$t('reports.statuses.to_do') },
  { value: 'reports.statuses.handed_over', text: proxy.$t('reports.statuses.handed_over') },
  { value: 'reports.statuses.completed', text: proxy.$t('reports.statuses.completed') },
  { value: 'reports.statuses.mediation', text: proxy.$t('reports.statuses.mediation') },
  { value: 'reports.statuses.in_progress', text: proxy.$t('reports.statuses.in_progress') },
  { value: 'reports.statuses.follow_up_user', text: proxy.$t('reports.statuses.follow_up_user') },
  { value: 'reports.statuses.reminder', text: proxy.$t('reports.statuses.reminder') },
  { value: 'reports.statuses.deleted', text: proxy.$t('reports.statuses.deleted') },
]

// Only the consequences of the rule book belong here. Anything else is recorded
// as "no consequence".
const consequenceOptions = [
  { value: null, text: proxy.$t('reports.no_consequence') },
  { value: 'reports.consequences.warning', text: proxy.$t('reports.consequences.warning') },
  { value: 'reports.consequences.yellow_card', text: proxy.$t('reports.consequences.yellow_card') },
  { value: 'reports.consequences.yellow_red_card', text: proxy.$t('reports.consequences.yellow_red_card') },
  { value: 'reports.consequences.red_card', text: proxy.$t('reports.consequences.red_card') },
]

// build suggestion lists (value => stored value, text => displayed label)
const statusSuggestList = statusOptions.map(s => ({ value: s.value, text: s.text, selectable: s.selectable !== false }))
const consequenceSuggestList = consequenceOptions.map(c => ({ value: c.value, text: c.text }))

// suggestion lists will be built after the translated option arrays are defined

// Helper function to find option objects from values
function findOption (val, list) {
  if (!val && val !== 0) return null
  const found = list.find(opt => opt.value === val)
  if (found) return found
  return { value: val, text: val }
}

function syncFormFromReport () {
  if (!props.report) return

  formData.status = findOption(props.report.status, statusSuggestList)
  formData.consequence = findOption(props.report.consequence, consequenceSuggestList)
  formData.forumThreadId = props.report.forumThreadId ?? null
  // Convert reminder_at (ISO) to separate date and time fields
  if (props.report.reminderAt) {
    const date = new Date(props.report.reminderAt)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    formData.reminderDate = `${year}-${month}-${day}`
    formData.reminderTime = `${hours}:${minutes}`
  } else {
    formData.reminderDate = null
    formData.reminderTime = null
  }
  error.value = null
}

// Watch both `show` and `report` props for changes.
// The callback destructures the new values array, grabbing just the first element (`show`).
// `immediate: true` ensures this runs when the component is first initialized as well.
watch(
  () => [props.show, props.report],
  ([show]) => {
    if (show) syncFormFromReport()
  },
  { immediate: true },
)

async function saveChanges () {
  loading.value = true
  error.value = null

  try {
    const updateData = {}

    // Handle status: either predefined option object or custom text string
    if (formData.status) {
      updateData.status = formData.status.value ?? formData.status.text ?? null
    }
    // Handle consequence: either predefined option object or custom text string
    if (formData.consequence) {
      updateData.consequence = formData.consequence.value ?? formData.consequence.text ?? null
    }
    if (formData.forumThreadId !== undefined) {
      updateData.forumThreadId = formData.forumThreadId
    }
    // Handle reminder: combine separate date + time inputs into ISO datetime or clear
    if (formData.reminderDate && formData.reminderTime) {
      updateData.reminderAt = `${formData.reminderDate}T${formData.reminderTime}:00`
    } else if (formData.reminderDate) {
      // date only -> set at start of day
      updateData.reminderAt = `${formData.reminderDate}T00:00:00`
    } else {
      updateData.reminderAt = null
    }

    await updateReport(props.report.id, updateData)

    emit('update', updateData)
  } catch (err) {
    if (err.jsonContent && err.jsonContent.message) {
      pulseError(proxy.$t(err.jsonContent.message))
    } else {
      pulseError(err.message || proxy.$t('reports.update_error'))
    }
    console.error(err)
  } finally {
    loading.value = false
  }
}

function doDelete () {
  emit('delete')
}
</script>
