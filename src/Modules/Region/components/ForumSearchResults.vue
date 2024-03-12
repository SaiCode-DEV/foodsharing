<template>
  <div class="mt-3 results">
    <div v-if="query" class="alert alert-info">
      <i class="fas fa-info-circle" />
      <span> {{ $i18n('search.thread-title-only') }} </span>
    </div>

    <div
      v-if="isEmpty && !isLoading && query.trim().length >= 3"
      class="dropdown-header alert alert-warning"
    >
      {{ $i18n('search.noresults') }}
    </div>

    <div v-if="!isEmpty" class="found-threads">
      <h3 class="dropdown-header">
        <i class="fas fa-comments" /> {{ $i18n('terminology.threads') }}
      </h3>

      <ThreadResultEntry
        v-for="thread in threads"
        :key="thread.id"
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
    threads: {
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
    isLoading: {
      type: Boolean,
      default: true,
    },
  },
  computed: {
    isEmpty () {
      return !this.threads.length
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
