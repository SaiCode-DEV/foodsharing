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

    <div v-else-if="props.titleThreads.length > 0" class="found-threads mb-4">
      <h3 class="dropdown-header">
        <i class="fas fa-heading" /> {{ $t('search.forum.found.title') }}
      </h3>

      <a
        v-for="thread in props.titleThreads"
        :key="'title-group-' + thread.id"
        class="thread-group title-result d-block dropdown-item"
        :href="$url('forumThread', thread.regionId, thread.id)"
      >
        <div class="thread-header">
          <h6 class="m-0 text-truncate d-inline">
            <i
              v-if="thread.pinnedLevel > 0"
              v-b-tooltip.noninteractive="$t('search.results.thread.sticky_tooltip')"
              class="fas fa-thumbtack"
            />
            <i
              v-else-if="thread.pinnedLevel < 0"
              v-b-tooltip.noninteractive="$t('search.results.thread.bottom_tooltip')"
              class="fas fa-sign-in-alt fa-rotate-90"
            />
            <i
              v-if="thread.isClosed"
              v-b-tooltip.noninteractive="$t('search.results.thread.closed_tooltip')"
              :class="{'ml-1': thread.pinnedLevel}"
              class="fas fa-lock"
            />
            {{ thread.name }}
          </h6>
          <small class="separate thread-metadata">
            <span>
              {{ $t('search.results.thread.last_post') }}
              {{ $dateFormatter.relativeTime(new Date(thread.lastPostSentAt)) }}
            </span>
          </small>
        </div>
      </a>
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

      <div
        v-for="groupData in sortedBodyThreads"
        :key="'body-group-' + groupData.id"
        class="thread-group"
      >
        <div class="thread-header">
          <h6 class="m-0 text-truncate d-inline">
            <i
              v-if="groupData.threads[0].stickiness > 0"
              v-b-tooltip.noninteractive="$t('search.results.thread.sticky_tooltip')"
              class="fas fa-thumbtack"
            />
            <i
              v-else-if="groupData.threads[0].stickiness < 0"
              v-b-tooltip.noninteractive="$t('search.results.thread.bottom_tooltip')"
              class="fas fa-sign-in-alt fa-rotate-90"
            />
            <i
              v-if="groupData.threads[0].is_closed"
              v-b-tooltip.noninteractive="$t('search.results.thread.closed_tooltip')"
              :class="{'ml-1': groupData.threads[0].stickiness}"
              class="fas fa-lock"
            />
            {{ groupData.threads[0].name }}
          </h6>
          <small class="separate thread-metadata">
            <span>
              {{ $t('search.results.thread.last_post') }}
              {{ $dateFormatter.relativeTime(new Date(groupData.threads[0].lastPostSentAt)) }}
            </span>
          </small>
        </div>
        <ForumSearchBody
          v-for="(thread, index) in groupData.threads"
          :key="'body-' + thread.id + '-' + index"
          :thread="thread"
          :query="query"
          :is-body="true"
          :hide-region="true"
          :is-grouped="groupData.threads.length > 1"
          :group-index="index"
          :group-total="groupData.threads.length"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, defineProps } from 'vue'
import ForumSearchBody from './ForumSearchBody.vue'

const props = defineProps({
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
})

const isEmpty = computed(() => {
  return props.titleThreads.length === 0 && props.bodyThreads.length === 0
})

// Group threads by thread ID, then sort each group by relevance
function groupAndSortThreads (threads) {
  const grouped = {}

  for (const thread of threads) {
    if (!grouped[thread.id]) {
      grouped[thread.id] = []
    }
    grouped[thread.id].push(thread)
  }

  // Sort threads within each group by relevance
  for (const threadId in grouped) {
    grouped[threadId].sort((a, b) => {
      const scoreA = calculateBlendedScore(a)
      const scoreB = calculateBlendedScore(b)
      return scoreB - scoreA
    })
  }

  // Sort the groups themselves by the highest scoring thread in each group
  // Factor in the number of matches in the group
  const sortedGroups = Object.keys(grouped).map(id => ({
    id,
    threads: grouped[id],
    score: calculateBlendedScore(grouped[id][0], grouped[id].length),
  })).sort((a, b) => {
    return b.score - a.score
  })
  // Return array to preserve order
  return sortedGroups
}

const sortedBodyThreads = computed(() => {
  // Filter out threads that are already in title results
  const titleThreadIds = new Set(props.titleThreads.map(thread => thread.id))
  const filteredBodyThreads = props.bodyThreads.filter(thread => !titleThreadIds.has(thread.id))
  return groupAndSortThreads(filteredBodyThreads)
})

function calculateBlendedScore (thread, matchCount = 1) {
  const relevance = Number(thread.relevance) || 0
  const threadDate = new Date(thread.time)
  const now = new Date()

  const ageInDays = (now - threadDate) / (1000 * 60 * 60 * 24)

  // Recency score: newer threads get higher scores
  // Use exponential decay with half-life of ~90 days
  const recencyScore = Math.exp(-ageInDays / 90)

  // Base score: 70% relevance, 30% recency
  const baseScore = (0.7 * relevance) + (0.3 * recencyScore)

  // Match count boost: stronger logarithmic scaling
  // 1 match: 1.0x, 2 matches: 1.3x, 3 matches: 1.48x, 5 matches: 1.7x, 10 matches: 2.0x
  const matchBoost = Math.pow(matchCount, 0.3)

  // Final score: base score multiplied by match boost
  const finalScore = baseScore * matchBoost

  return finalScore
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

.thread-group {
  margin-bottom: 1rem;
  border-left: 3px solid var(--fs-color-primary-alpha-20, rgba(121, 164, 48, 0.2));
  padding-left: 0.5rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.thread-header {
  padding: 0.5rem 0;

  .thread-metadata {
    display: block;
    margin-top: 0.25rem;
    color: var(--fs-color-gray-500);
  }
}

.title-result:hover {
  background-color: var(--fs-color-gray-200);
}
</style>
