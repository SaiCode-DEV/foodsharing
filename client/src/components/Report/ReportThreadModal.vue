<template>
  <b-modal
    :visible="show"
    :title="$t('reports.forum_thread_search')"
    size="lg"
    @hidden="$emit('close')"
  >
    <div>
      <b-form-group
        :label="$t('reports.search')"
        label-for="thread-search"
      >
        <b-form-input
          id="thread-search"
          v-model="query"
          :placeholder="$t('reports.search_by_name_or_id')"
        />
      </b-form-group>

      <b-alert
        v-if="error"
        variant="danger"
        class="mt-3"
      >
        {{ error }}
      </b-alert>

      <div v-if="isLoading" class="mt-2">
        <b-spinner small /> {{ $t('reports.searching') }}
      </div>

      <div v-if="results && results.length" class="mt-3">
        <b-list-group>
          <b-list-group-item
            v-for="thread in results"
            :key="thread.id"
            class="d-flex justify-content-between align-items-center"
          >
            <div>
              <strong>{{ thread.name }}</strong>
            </div>
            <div>
              <b-button
                size="sm"
                variant="link"
                :href="urls.forumThread(props.regionId, thread.id)"
                target="_blank"
              >
                {{ $t('reports.view') }}
              </b-button>
              <b-button
                size="sm"
                variant="success"
                @click="selectThread(thread.id)"
              >
                {{ $t('reports.select_thread') }}
              </b-button>
            </div>
          </b-list-group-item>
        </b-list-group>
      </div>

      <b-alert
        v-else-if="!isLoading"
        show
        variant="info"
        class="mt-3"
      >
        {{ $t('reports.noresults') }}
      </b-alert>

      <!-- Create thread button shown at bottom (not as per-item action) -->
      <div class="mt-3">
        <b-button
          variant="primary"
          @click="createThread"
        >
          {{ $t('reports.create_thread') }}
        </b-button>
      </div>
    </div>

    <template #modal-footer>
      <b-button
        variant="secondary"
        @click="$emit('close')"
      >
        {{ $t('button.cancel') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { searchForum } from '@/api/search'
import { createThread as createForumThread } from '@/api/forum'
import { urls } from '@/helper/urls'
import t, { i18nInstance } from '@/helper/i18n'

const props = defineProps({
  show: { type: Boolean, default: false },
  regionId: { type: Number, required: true },
  reportId: { type: Number, required: false, default: null },
  report: { type: Object, required: false, default: null },
})

const emit = defineEmits(['selected', 'close'])

const query = ref(props.reportId ? String(props.reportId) : '')
const results = ref([])
const error = ref(null)
const isLoadingTitle = ref(false)
const isLoadingBody = ref(false)
const isLoading = ref(false)
let timeout = null

watch(query, (newQuery) => {
  if (newQuery.trim().length > 2) {
    delayedFetch()
  } else {
    clearTimeout(timeout)
    results.value = []
    isLoading.value = false
  }
})

function delayedFetch () {
  isLoading.value = true
  isLoadingTitle.value = true
  isLoadingBody.value = true
  if (timeout) clearTimeout(timeout)
  timeout = setTimeout(fetchResults, 500)
}

async function fetchResults () {
  const curQuery = query.value
  if (!props.regionId) {
    error.value = t('reports.error_missing_region')
    isLoadingTitle.value = false
    isLoadingBody.value = false
    isLoading.value = false
    return
  }

  // search titles
  searchForum(props.regionId, 0, curQuery, false).then(res => {
    if (curQuery !== query.value) return
    const titleResults = res || []
    isLoadingTitle.value = false
    mergeResults(titleResults)
    updateLoading()
  }).catch(err => {
    error.value = err.message || t('reports.search_error')
    isLoadingTitle.value = false
    updateLoading()
  })

  // search bodies
  searchForum(props.regionId, 0, curQuery, true).then(res => {
    if (curQuery !== query.value) return
    const bodyResults = res || []
    isLoadingBody.value = false
    mergeResults(bodyResults)
    updateLoading()
  }).catch(err => {
    error.value = err.message || t('reports.search_error')
    isLoadingBody.value = false
    updateLoading()
  })
}

function mergeResults (newList) {
  const map = new Map(results.value.map(r => [r.id, r]))
  for (const item of newList) map.set(item.id, item)
  results.value = Array.from(map.values())
}

function updateLoading () {
  isLoading.value = isLoadingTitle.value || isLoadingBody.value
}

function selectThread (tid) {
  emit('selected', tid)
}

async function createThread () {
  if (!props.regionId || !props.report) {
    error.value = t('reports.error_missing_region')
    return
  }

  try {
    // Generate title: {{reportId}} - {{Reported Name}}({{id}}), {{reason}}
    const reported = props.report.reported
    const title = `${props.report.id} - ${reported.name}(${reported.id}), ${props.report.reason}`

    // Generate body from report details
    const reportedName = `${reported.name || ''} ${reported.lastName || ''}`.trim()
    const reporterName = `${props.report.reporter.name || ''} ${props.report.reporter.lastName || ''}`.trim()
    const storeName = props.report.store ? `\n**${t('reports.store')}:** ${props.report.store.name} (${props.report.store.id})` : ''
    const body = `**${t('reports.report_id')}:** ${props.report.id}
**${t('reports.time')}:** ${i18nInstance.global.d(new Date(props.report.reportedAt), 'long')} ${storeName}
**${t('reports.reported')}:** ${reportedName} (${reported.id}), ${reported.mail}
**${t('reports.reporter')}:** ${reporterName} (${props.report.reporter.id}), ${props.report.reporter.mail}
**${t('reports.reason')}:** ${props.report.reason}
**${t('reports.message')}:** ${props.report.message}`

    const thread = await createForumThread(props.regionId, 0, title, body)
    if (thread && thread.id) {
      emit('selected', thread.id)
      emit('close')
    }
  } catch (err) {
    error.value = err.message || t('reports.search_error')
  }
}
</script>

<style scoped>
.small { font-size: 0.8rem }
</style>
