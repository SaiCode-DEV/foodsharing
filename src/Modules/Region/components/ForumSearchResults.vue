<template>
  <div class="mt-3 results">
    <div
      v-if="isEmpty && !isLoadingTitle && !isLoadingBody && query.trim().length >= 3"
      class="dropdown-header alert alert-warning"
    >
      {{ $t('search.noresults') }}
    </div>

    <!-- Title results section -->
    <div v-if="isLoadingTitle" class="skeleton-container">
      <div class="found-threads mb-4">
        <h3 class="dropdown-header">
          <i class="fas fa-heading" /> {{ $t('search.forum.found.title') }}
        </h3>
        <div
          v-for="i in 3"
          :key="'skeleton-title-' + i"
          class="skeleton-thread-item"
        >
          <b-skeleton width="85%" />
          <b-skeleton width="30%" height="1rem" />
        </div>
      </div>
    </div>

    <div v-else-if="sortedTitleThreads.length > 0" class="found-threads mb-4">
      <h3 class="dropdown-header">
        <i class="fas fa-heading" /> {{ $t('search.forum.found.title') }}
      </h3>

      <ThreadResultEntry
        v-for="thread in sortedTitleThreads"
        :key="'title-' + thread.id"
        :thread="thread"
        :hide-region="true"
      />
    </div>

    <!-- Body results section -->
    <div v-if="isLoadingBody" class="skeleton-container">
      <div class="found-threads">
        <h3 class="dropdown-header">
          <i class="fas fa-align-left" /> {{ $t('search.forum.found.body') }}
        </h3>
        <div
          v-for="i in 3"
          :key="'skeleton-body-' + i"
          class="skeleton-thread-item"
        >
          <b-skeleton width="85%" />
          <b-skeleton width="30%" height="1rem" />
        </div>
      </div>
    </div>

    <div v-else-if="sortedBodyThreads.length > 0" class="found-threads">
      <h3 class="dropdown-header">
        <i class="fas fa-align-left" /> {{ $t('search.forum.found.body') }}
      </h3>

      <ThreadResultEntry
        v-for="thread in sortedBodyThreads"
        :key="'body-' + thread.id"
        :thread="thread"
        :hide-region="true"
      />
    </div>
  </div>
</template>

<script>
import ThreadResultEntry from '@/components/SearchBar/ResultEntry/ThreadResultEntry'

export default {
  components: { ThreadResultEntry },
  props: {
    titleThreads: {
      type: Array,
      default: () => [],
    },
    bodyThreads: {
      type: Array,
      default: () => [],
    },
    groupId: {
      type: Number,
      required: true,
    },
    subforumId: {
      type: Number,
      required: true,
    },
    query: {
      type: String,
      default: '',
    },
    isLoadingTitle: {
      type: Boolean,
      default: false,
    },
    isLoadingBody: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    isEmpty () {
      return this.titleThreads.length === 0 && this.bodyThreads.length === 0
    },
    sortedTitleThreads () {
      return this.sortThreads(this.titleThreads)
    },
    sortedBodyThreads () {
      // Filter out threads that are already in title results
      const titleIds = new Set(this.titleThreads.map(thread => thread.id))
      const uniqueBodyThreads = this.bodyThreads.filter(thread => !titleIds.has(thread.id))
      return this.sortThreads(uniqueBodyThreads)
    },
  },
  methods: {
    sortThreads (threads) {
      const sorted = [...threads]
      // Blended sorting: combines relevance with recency
      sorted.sort((a, b) => {
        const scoreA = this.calculateBlendedScore(a)
        const scoreB = this.calculateBlendedScore(b)
        return scoreB - scoreA
      })
      return sorted
    },
    calculateBlendedScore (thread) {
      const relevance = Number(thread.relevance) || 0
      const threadDate = new Date(thread.time)
      const now = new Date()

      const ageInDays = (now - threadDate) / (1000 * 60 * 60 * 24)

      // Recency score: newer threads get higher scores
      // Use exponential decay with half-life of ~90 days
      const recencyScore = Math.exp(-ageInDays / 90)

      // Blended score: 60% relevance, 40% recency
      return (0.6 * relevance) + (0.4 * recencyScore)
    },
  },
}
</script>

<style lang="scss" scoped>
.dropdown-header {
    white-space: normal;
    margin-bottom: 0;
}

.found-threads ::v-deep a {
  font-size: 0.9rem;

  // teaser == date of last thread update
  & > small {
    float: right;
    margin: 0.1rem 0;
    color: var(--fs-color-gray-500);
  }
}

::v-deep .found-threads > .dropdown-item,
::v-deep .found-threads > .dropdown-header {
  padding-left: 0;
  padding-right: 0;
}

</style>
