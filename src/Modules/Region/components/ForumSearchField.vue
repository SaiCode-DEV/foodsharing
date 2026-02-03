<template>
  <div id="forum-search" class="form m-2">
    <div
      ref="foruminputgroup"
      class="input-group input-group-sm"
    >
      <span class="input-group-prepend">
        <label
          id="forum-searchfield-label"
          :aria-label="$t('search.title')"
          class="input-group-text"
          for="forum-searchfield"
        >
          <img
            v-if="isLoading"
            src="/img/469.gif"
            alt="loading"
          >
          <i v-else class="fas fa-search" />
        </label>
      </span>
      <input
        id="forum-searchfield"
        v-model="query"
        :placeholder="$t('search.forum.placeholder')"
        type="text"
        class="form-control"
        aria-labelledby="forum-searchfield-label"
        aria-placeholder=""
      >
      <span class="input-group-append">
        <button
          v-if="query.trim().length > 0"
          type="button"
          class="btn btn-outline-secondary"
          aria-label="Clear search"
          @click="clearSearch"
        >
          <i class="fas fa-times" />
        </button>
      </span>
    </div>
    <div v-if="isOpen" id="forum-search-results">
      <ForumSearchResults
        :title-threads="titleThreads || []"
        :body-threads="bodyThreads || []"
        :group-id="groupId"
        :subforum-id="subforumId"
        :query="query"
        :is-loading-title="isLoadingTitle"
        :is-loading-body="isLoadingBody"
        @close="close"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue'
import ForumSearchResults from './ForumSearchResults'
import { searchForum } from '@/api/search'

const props = defineProps({
  groupId: {
    type: Number,
    default: -1,
  },
  subforumId: {
    type: Number,
    required: true,
  },
})

const emit = defineEmits(['search-active'])

const query = ref('')
const isOpen = ref(false)
const isLoading = ref(false)
const isLoadingTitle = ref(false)
const isLoadingBody = ref(false)
const titleThreads = ref([])
const bodyThreads = ref([])
let timeout = null

watch(query, (newQuery) => {
  if (newQuery.trim().length > 2) {
    open()
    delayedFetch()
  } else {
    clearTimeout(timeout)
    close()
    isLoading.value = false
    titleThreads.value = []
    bodyThreads.value = []
  }
})

function open () {
  isOpen.value = true
  emit('search-active', true)
}

function delayedFetch () {
  isLoading.value = true
  isLoadingTitle.value = true
  isLoadingBody.value = true
  if (timeout) {
    clearTimeout(timeout)
  }
  timeout = setTimeout(() => {
    fetch()
  }, 500)
}

function close () {
  isOpen.value = false
  emit('search-active', false)
}

function clearSearch () {
  query.value = ''
  titleThreads.value = []
  bodyThreads.value = []
  close()
}

async function fetch () {
  const curQuery = query.value
  if (query.value.trim().length === 0) return
  open()

  // Fetch title results
  searchForum(props.groupId, props.subforumId, curQuery, false).then(res => {
    if (curQuery !== query.value) {
      // query has changed, throw away this response
      return
    }
    titleThreads.value = res
    isLoadingTitle.value = false
    updateLoadingState()
  })

  // Fetch body results independently
  searchForum(props.groupId, props.subforumId, curQuery, true).then(res => {
    if (curQuery !== query.value) {
      // query has changed, throw away this response
      return
    }
    bodyThreads.value = res
    isLoadingBody.value = false
    updateLoadingState()
  })
}

function updateLoadingState () {
  isLoading.value = isLoadingTitle.value || isLoadingBody.value
}

</script>

<style lang="scss" scoped>
#forum-search {
  display: block;
}
</style>
