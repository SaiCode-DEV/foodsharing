<template>
  <div class="px-4 py-0">
    <!-- Search and Filter -->
    <b-row class="mb-3">
      <b-col md="6">
        <b-form-group :label="$t('reports.search')" label-for="search-input">
          <b-form-input
            id="search-input"
            v-model="searchQuery"
            :placeholder="$t('reports.search_by_name_or_id')"
            type="text"
          />
        </b-form-group>
      </b-col>
      <b-col md="6">
        <b-form-group :label="$t('reports.filter_status')" label-for="status-filter">
          <vSelect
            id="status-filter"
            v-model="cleanedSelectedStatus"
            :options="allStatusOptions"
            label="text"
            multiple
            :reduce="reduceOption"
            :close-on-select="false"
            clearable
          />
        </b-form-group>
      </b-col>
    </b-row>

    <!-- Reports Table or Loading -->
    <b-skeleton-table
      v-if="!reports"
      :rows="3"
      :columns="6"
    />
    <div v-else-if="filteredReports.length">
      <b-table
        ref="reportTable"
        :fields="fields"
        :items="filteredReports"
        :current-page="currentPage"
        :per-page="perPage"
        striped
        hover
        responsive
        @row-clicked="toggleDetails"
      >
        <template #cell(time)="row">
          {{ $d(new Date(row.item.reportedAt), 'shortDateTime') }}
        </template>
        <template #cell(reported)="row">
          <Avatar :user="row.item.reported" />
          <router-link v-if="row.item.reported.name" :to="$url('profile', row.item.reported.id)">
            {{ row.item.reported.name }}
          </router-link>
          <span v-else v-text="$t('forum.deleted_user')" />
          <i
            v-if="row.item.reported.mail"
            v-b-tooltip="row.item.reported.mail"
            class="fas fa-envelope ml-1 cursor-pointer"
            @click.stop="copyToClipboard(row.item.reported.mail)"
          />
        </template>
        <template #cell(reporter)="row">
          <Avatar :user="row.item.reporter" />
          <router-link :to="$url('profile', row.item.reporter.id)">
            {{ row.item.reporter.name }}
          </router-link>
          <i
            v-b-tooltip="row.item.reporter.mail"
            class="fas fa-envelope ml-1 cursor-pointer"
            @click.stop="copyToClipboard(row.item.reporter.mail)"
          />
        </template>

        <template #cell(status)="row">
          <b-badge
            v-if="row.item.status"
            :variant="getStatusVariant(row.item.status)"
          >
            {{ isTranslatableStatus(row.item.status) ? $t(row.item.status) : row.item.status }}
          </b-badge>
          <span v-else class="text-muted">{{ $t('reports.no_status') }}</span>
        </template>

        <template #cell(reminder)="row">
          <span v-if="row.item.reminderAt" :title="dateFormatter.dateTime(row.item.reminderAt)">
            <i class="fas fa-bell" /> <Time
              :time="row.item.reminderAt"
              :muted="false"
              :show-icon="false"
            />
          </span>
          <span v-else class="text-muted">-</span>
        </template>

        <template #cell(actions)="row">
          <b-button-group>
            <b-button
              size="sm"
              :variant="row.item.forumThreadId ? 'primary' : 'outline-secondary'"
              @click.stop="openThread(row.item, $event)"
              @auxclick.stop="openThread(row.item, $event)"
            >
              <i class="fas fa-comment-alt" />
            </b-button>
            <b-button
              size="sm"
              variant="primary"
              @click.stop="editReport(row.item)"
            >
              <i class="fas fa-edit" />
            </b-button>
          </b-button-group>
        </template>

        <template #row-details="row">
          <div class="report">
            <p><strong>{{ $t('reports.report_id') }}</strong>: {{ row.item.id }}</p>
            <p><strong>{{ $t('reports.time') }}</strong>: {{ $d(new Date(row.item.reportedAt), 'long') }}</p>
            <p v-if="row.item.store?.id > 0">
              <strong>{{ $t('reports.store') }}</strong>: <router-link :to="$url('store', row.item.store.id)">
                {{ row.item.store.name }}
              </router-link> ({{ row.item.store.id }})
            </p>
            <p v-else-if="row.item.store?.id === 0">
              <strong>{{ $t('reports.store') }}</strong>: {{ $t('reports.deleted_store') }}
            </p>
            <p v-else>
              <strong>{{ $t('reports.store') }}</strong>: -
            </p>
            <p><strong>{{ $t('reports.reported') }}</strong>: {{ row.item.reported.name }} {{ row.item.reported.lastName }} ({{ row.item.reported.id }}), {{ row.item.reported.mail }}</p>
            <p><strong>{{ $t('reports.reporter') }}</strong>: {{ row.item.reporter.name }} {{ row.item.reporter.lastName }} ({{ row.item.reporter.id }}), {{ row.item.reporter.mail }}</p>
            <p><strong>{{ $t('reports.reason') }}</strong>: {{ row.item.reason }}</p>
            <p><strong>{{ $t('reports.message') }}</strong>: {{ row.item.message }}</p>
            <p v-if="row.item.reminderAt">
              <strong>{{ $t('reports.reminder_at') }}</strong>: {{ $d( new Date(row.item.reminderAt), 'long') }}
            </p>
            <p v-if="row.item.status">
              <strong>{{ $t('reports.status') }}</strong>: {{ isTranslatableStatus(row.item.status) ? $t(row.item.status) : row.item.status }}
            </p>
            <p v-if="row.item.consequence">
              <strong>{{ $t('reports.consequence') }}</strong>: {{ isTranslatableStatus(row.item.consequence) ? $t(row.item.consequence) : row.item.consequence }}
            </p>
          </div>
        </template>
      </b-table>
      <div class="float-right">
        <b-pagination
          v-if="filteredReports.length > perPage"
          v-model="currentPage"
          :total-rows="filteredReports.length"
          :per-page="perPage"
        />
      </div>
    </div>
    <b-alert
      v-else
      show
    >
      {{ $t('reports.no_reports_fallback') }}
    </b-alert>

    <!-- Edit Modal -->
    <ReportEditModal
      v-if="selectedReport"
      :report="selectedReport"
      :show="showEditModal"
      :may-delete="mayDelete"
      :region-id="props.regionReportGroupId"
      @update="handleUpdate"
      @delete="handleDeleteFromModal"
      @close="closeModal"
    />

    <ReportThreadModal
      v-if="showThreadModal"
      :show="showThreadModal"
      :region-id="props.regionReportGroupId"
      :report-id="threadModalReport?.id"
      :report="threadModalReport"
      @selected="onThreadSelected"
      @close="closeThreadModal"
    />
  </div>
</template>
<script setup>
import { ref, computed, getCurrentInstance, watch } from 'vue'
import vSelect from 'vue-select'
import 'css/vue-select.css'
import Avatar from '@/components/Avatar/Avatar.vue'
import ReportEditModal from './ReportEditModal.vue'
import ReportThreadModal from './ReportThreadModal.vue'
import { useUserStore } from '@/stores/user'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { urls } from '@/helper/urls'
import { navigate } from '@/helper/router'
import dateFormatter from '@/helper/date-formatter'
import { pulseSuccess, pulseError } from '@/script'
import { deleteReport as apiDeleteReport, updateReport as apiUpdateReport } from '@/api/report'

const props = defineProps({
  reports: { type: Array, default: null },
  regionId: { type: Number, required: false, default: null },
  regionReportGroupId: { type: Number, required: false, default: null },
})

const userStore = useUserStore()
const { proxy } = getCurrentInstance()
const { confirmationDialogue } = useConfirmationDialogue()

const currentPage = ref(1)
const perPage = ref(20)
const searchQuery = ref('')
// default selected statuses: Open (to_do) and "In Bearbeitung" (in_progress)
const STORAGE_KEY = 'reportSelectedStatuses'
function loadSelectedStatuses () {
  try {
    if (typeof localStorage === 'undefined') return ['reports.statuses.to_do', 'reports.statuses.in_progress']
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return ['reports.statuses.to_do', 'reports.statuses.in_progress']
    const parsed = JSON.parse(raw)
    if (Array.isArray(parsed)) {
      // Filter out null/undefined values that may have been stored
      const filtered = parsed.filter(v => v !== null && v !== undefined)
      return filtered.length > 0 ? filtered : ['reports.statuses.to_do', 'reports.statuses.in_progress']
    }
    return ['reports.statuses.to_do', 'reports.statuses.in_progress']
  } catch (e) {
    return ['reports.statuses.to_do', 'reports.statuses.in_progress']
  }
}

const selectedStatus = ref(loadSelectedStatuses())

// Clean up any null/undefined values that might already exist
const cleanedSelectedStatus = computed({
  get: () => {
    const vals = selectedStatus.value || []
    return vals.filter(v => v !== null && v !== undefined)
  },
  set: (val) => {
    selectedStatus.value = val
  },
})

// persist selected statuses
watch(selectedStatus, (val) => {
  try {
    if (typeof localStorage !== 'undefined') {
      // Filter out null/undefined values before saving
      const cleanedVal = (val || []).filter(v => v !== null && v !== undefined)
      localStorage.setItem(STORAGE_KEY, JSON.stringify(cleanedVal))
    }
  } catch (e) {
    // ignore storage errors
  }
}, { deep: true })
const showEditModal = ref(false)
const selectedReport = ref(null)
const showThreadModal = ref(false)
const threadModalReport = ref(null)

const fields = computed(() => [
  { key: 'id', label: proxy.$t('reports.id'), sortable: true },
  { key: 'time', label: proxy.$t('reports.time'), sortable: true },
  { key: 'reported', label: proxy.$t('reports.reported'), sortable: true },
  { key: 'reporter', label: proxy.$t('reports.reporter'), sortable: true },
  { key: 'reason', label: proxy.$t('reports.reason'), sortable: true },
  { key: 'status', label: proxy.$t('reports.status'), sortable: true },
  { key: 'reminder', label: proxy.$t('reports.reminder') },
  { key: 'actions', label: '' },
])

function copyToClipboard (text, messageKey = 'copied_to_clipboard', params = {}) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      if (messageKey) pulseSuccess(proxy.$t(messageKey, { text, ...params }))
    })
  }
}

const mayDelete = computed(() => userStore.isOrga)

const statusOptions = computed(() => [
  { value: 'reports.statuses.to_do', text: proxy.$t('reports.statuses.to_do') },
  { value: 'reports.statuses.handed_over', text: proxy.$t('reports.statuses.handed_over') },
  { value: 'reports.statuses.completed', text: proxy.$t('reports.statuses.completed') },
  { value: 'reports.statuses.mediation', text: proxy.$t('reports.statuses.mediation') },
  { value: 'reports.statuses.in_progress', text: proxy.$t('reports.statuses.in_progress') },
  { value: 'reports.statuses.follow_up_user', text: proxy.$t('reports.statuses.follow_up_user') },
  { value: 'reports.statuses.reminder', text: proxy.$t('reports.statuses.reminder') },
  { value: 'reports.statuses.deleted', text: proxy.$t('reports.statuses.deleted') },
])

// Combine predefined options with any custom status strings present in reports
const allStatusOptions = computed(() => {
  const predefined = statusOptions.value || []
  const predefinedValues = new Set(predefined.map(o => o.value))

  const custom = []
  if (props.reports && Array.isArray(props.reports)) {
    props.reports.forEach(r => {
      const s = r.status
      if (s && !predefinedValues.has(s) && !custom.find(c => c.value === s)) {
        custom.push({ value: s, text: isTranslatableStatus(s) ? proxy.$t(s) : s })
      }
    })
  }

  return [...predefined, ...custom]
})

// reduce function so vSelect returns values (strings) instead of whole option objects
const reduceOption = (opt) => {
  // Safely handle null/undefined options
  if (!opt) return undefined
  return opt?.value
}

const filteredReports = computed(() => {
  if (!props.reports) return []

  return props.reports.filter(report => {
    const sel = cleanedSelectedStatus.value || []
    if (Array.isArray(sel) && sel.length > 0 && !sel.includes('')) {
      if (!sel.includes(report.status)) return false
    }

    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      const name = `${report.reported.name || ''}`.toLowerCase()
      const id = (report.reported?.id ?? '').toString()
      const reason = (report.reason || '').toLowerCase()
      if (!name.includes(query) && !id.includes(query) && !reason.includes(query)) return false
    }

    return true
  })
})

function toggleDetails (report, index) {
  const expanded = filteredReports.value.find((r, i) => i !== index && r._showDetails)
  if (expanded) expanded._showDetails = false
  report._showDetails = !report._showDetails
}

function isTranslatableStatus (status) {
  return status && (status.startsWith('reports.statuses.') || status.startsWith('reports.consequences.'))
}

function getStatusVariant (status) {
  const baseVariants = {
    'reports.statuses.to_do': 'danger',
    'reports.statuses.handed_over': 'info',
    'reports.statuses.completed': 'success',
    'reports.statuses.mediation': 'info',
    'reports.statuses.in_progress': 'warning',
    'reports.statuses.follow_up_user': 'info',
    'reports.statuses.reminder': 'info',
    'reports.statuses.deleted': 'dark',
  }

  if (baseVariants[status]) {
    return baseVariants[status]
  }

  for (const [key, variant] of Object.entries(baseVariants)) {
    if (proxy.$t(key, 'de') === status || proxy.$t(key, 'en') === status) {
      return variant
    }
  }

  return 'primary'
}

function editReport (report) {
  selectedReport.value = report
  showEditModal.value = true
}

function closeModal () {
  showEditModal.value = false
  selectedReport.value = null
}

function openThread (report, event) {
  const url = urls.forumThread(props.regionReportGroupId, report.forumThreadId)

  // If thread is already linked, navigate to it. Open in new tab only on middle-click.
  if (report.forumThreadId) {
    const isMiddle = event && (event.button === 1 || event.type === 'auxclick')
    if (isMiddle) {
      window.open(url, '_blank')
    } else {
      navigate(url)
    }
    return
  }

  threadModalReport.value = report
  showThreadModal.value = true
}

function closeThreadModal () {
  showThreadModal.value = false
  threadModalReport.value = null
}

async function onThreadSelected (tid) {
  if (!threadModalReport.value) return
  const success = await updateReportData({ reportId: threadModalReport.value.id, forumThreadId: tid })
  closeThreadModal()
  if (success) {
    navigate(urls.forumThread(props.regionReportGroupId, tid))
  }
}

async function handleUpdate (updatedData) {
  await updateReportData({ reportId: selectedReport.value.id, ...updatedData })
  closeModal()
}

async function handleDeleteFromModal () {
  await deleteReportData(selectedReport.value.id)
  closeModal()
}

async function updateReportData ({ reportId, status, consequence, forumThreadId, reminderAt }) {
  const report = props.reports.find(r => r.id === reportId)
  // The endpoint stores every field of the request, so always send the complete
  // editable state and fall back to the values of the loaded report.
  const currentValue = (value, fallback) => value !== undefined ? value : fallback ?? null
  try {
    await apiUpdateReport(reportId, {
      status: currentValue(status, report?.status),
      consequence: currentValue(consequence, report?.consequence),
      forumThreadId: currentValue(forumThreadId, report?.forumThreadId),
      reminderAt: currentValue(reminderAt, report?.reminderAt),
    })
    if (report) {
      if (status !== undefined) report.status = status
      if (consequence !== undefined) report.consequence = consequence
      if (forumThreadId !== undefined) report.forumThreadId = forumThreadId
      if (reminderAt !== undefined) report.reminderAt = reminderAt
    }
    return true
  } catch (err) {
    if (err.jsonContent && err.jsonContent.message) {
      pulseError(proxy.$t(err.jsonContent.message))
    } else {
      pulseError(err.message || proxy.$t('reports.update_error'))
    }
    console.error('Error updating report:', err)
    return false
  }
}

async function deleteReportData (id) {
  if (!await confirmationDialogue('profile.report.confirmDelete')) return
  try {
    await apiDeleteReport(id)
  } catch (err) {
    console.error('Error deleting report:', err)
  }
}
</script>
<style scoped>
::v-deep tr:not(.b-table-details) {
  cursor: pointer;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
