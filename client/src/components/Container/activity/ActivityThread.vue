<template>
  <div>
    <ActivityPost
      v-for="(post, index) in showActivePosts"
      :key="index"
      class="activity-item"
      v-bind="post"
    />
    <button
      v-if="!loading && !end && showActivePosts.length !== 0"
      class="list-group-item small activity-item list-group-item-secondary list-group-item-action font-weight-bold text-center"
      @click="fetchUpdates"
      v-text="$t('globals.show_more')"
    />
    <li
      v-if="loading"
      slot="no-results"
      class="list-group-item activity-item list-group-item-warning text-center"
    >
      <i class="fas fa-spinner fa-spin" />
    </li>
    <li
      v-if="showActivePosts.length === 0"
      slot="no-results"
      class="list-group-item activity-item list-group-item-warning text-center"
      v-text="$t('dashboard.no_updates')"
    />
    <li
      v-if="end && showActivePosts.length !== 0"
      class="list-group-item activity-item list-group-item-info text-center"
      v-text="$t('dashboard.no_more_updates_' + activeType)"
    />
  </div>
</template>

<script>
import DataUpdates from '@/stores/updates'
import ActivityPost from './ActivityPost'

export default {
  components: { ActivityPost },
  props: {
    displayedType: {
      type: String,
      default: 'all',
    },
  },
  data () {
    return {
      updates: [],
      page: 0,
      loading: false,
      end: false,
    }
  },
  computed: {
    showActivePosts () {
      if (this.activeType === 'all') {
        return this.updates
      }
      return this.updates.filter(a => this.activeType === a.type)
    },
    activeType () {
      return this.displayedType
    },
  },
  created () {
    this.fetchUpdates()
  },
  methods: {
    async fetchUpdates () {
      try {
        this.loading = true
        const updates = await DataUpdates.mutations.fetch(this.page)
        updates.forEach(update => {
          update.time = new Date(Date.parse(update.time))
        })
        updates.sort((a, b) => {
          return b.time > a.time ? 1 : -1
        })

        if (updates.length > 0) {
          this.updates.push(...updates)
          this.page++
          this.end = false
        } else {
          this.end = true
        }
      } catch (e) {
        console.error(e)
      } finally {
        this.loading = false
      }
    },
    async reloadData () {
      this.page = 0
      this.updates = []
      this.end = false
      this.fetchUpdates()
    },
  },
}
</script>

<style lang="scss" scoped>
::v-deep.activity-item:not(:last-child) {
  border-bottom: 0;
}

::v-deep.activity-item:last-child {
  border-bottom-left-radius: var(--border-radius);
  border-bottom-right-radius: var(--border-radius);
}

::v-deep.clickable {
  cursor: pointer;
}
</style>
