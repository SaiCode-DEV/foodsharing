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
      <forum-search-results
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

<script>
import ForumSearchResults from './ForumSearchResults'
import { searchForum } from '@/api/search'

export default {
  components: { ForumSearchResults },
  props: {
    groupId: {
      type: Number,
      default: -1,
    },
    subforumId: {
      type: Number,
      required: true,
    },
  },
  data () {
    return {
      query: '',
      isOpen: false,
      isLoading: false,
      isLoadingTitle: false,
      isLoadingBody: false,
      titleThreads: [],
      bodyThreads: [],
    }
  },
  watch: {
    query (query) {
      if (query.trim().length > 2) {
        this.open()
        this.delayedFetch()
      } else {
        clearTimeout(this.timeout)
        this.close()
        this.isLoading = false
        this.titleThreads = []
        this.bodyThreads = []
      }
    },
  },

  methods: {
    open () {
      this.isOpen = true
      this.$emit('search-active', true)
    },
    delayedFetch () {
      this.isLoading = true
      this.isLoadingTitle = true
      this.isLoadingBody = true
      if (this.timeout) {
        clearTimeout(this.timeout)
        this.timer = null
      }
      this.timeout = setTimeout(() => {
        this.fetch()
      }, 500)
    },
    close () {
      this.isOpen = false
      this.$emit('search-active', false)
    },
    clearSearch () {
      this.query = ''
      this.titleThreads = []
      this.bodyThreads = []
      this.close()
    },
    async fetch () {
      const curQuery = this.query
      if (this.query.trim().length === 0) return
      this.open()

      // Fetch title results
      searchForum(this.groupId, this.subforumId, curQuery, false).then(res => {
        if (curQuery !== this.query) {
          // query has changed, throw away this response
          return
        }
        this.titleThreads = res
        this.isLoadingTitle = false
        this.updateLoadingState()
      })

      // Fetch body results independently
      searchForum(this.groupId, this.subforumId, curQuery, true).then(res => {
        if (curQuery !== this.query) {
          // query has changed, throw away this response
          return
        }
        this.bodyThreads = res
        this.isLoadingBody = false
        this.updateLoadingState()
      })
    },
    updateLoadingState () {
      this.isLoading = this.isLoadingTitle || this.isLoadingBody
    },
    clickOutListener () {
      this.isOpen = false
      this.$emit('search-active', false)
    },
  },
}
</script>

<style lang="scss" scoped>
#forum-search {
  display: block;
}
</style>
