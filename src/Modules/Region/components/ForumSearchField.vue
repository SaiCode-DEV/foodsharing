<template>
  <div id="forum-search" class="form m-2">
    <div
      ref="foruminputgroup"
      class="input-group input-group-sm"
    >
      <span class="input-group-prepend">
        <label
          id="forum-searchfield-label"
          :aria-label="$i18n('search.title')"
          class="input-group-text text-primary"
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
        :placeholder="$i18n('search.forum')"
        type="text"
        class="form-control text-primary"
        aria-labelledby="forum-searchfield-label"
        aria-placeholder=""
      >
    </div>
    <div v-if="isOpen" id="forum-search-results">
      <forum-search-results
        :threads="threads || []"
        :group-id="groupId"
        :subforum-id="subforumId"
        :query="query"
        :is-loading="isLoading"
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
      threads: [],
    }
  },
  watch: {
    query (query) {
      if (query.trim().length > 2) {
        this.open()
        this.delayedFetch()
      } else if (query.trim().length) {
        clearTimeout(this.timeout)
        this.open()
        this.isLoading = false
        this.threads = []
      } else {
        clearTimeout(this.timeout)
        this.close()
        this.isLoading = false
        this.threads = []
      }
    },
  },
  methods: {
    open () {
      this.isOpen = true
    },
    delayedFetch () {
      this.isLoading = true
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
    },
    async fetch () {
      const curQuery = this.query
      const res = await searchForum(this.groupId, this.subforumId, curQuery)
      if (curQuery !== this.query) {
        // query has changed, throw away this response
        return false
      }
      this.threads = res
      this.isLoading = false
    },
    clickOutListener () {
      this.isOpen = false
    },
  },
}
</script>

<style lang="scss" scoped>
#forum-search {
  display: block;
}
</style>
