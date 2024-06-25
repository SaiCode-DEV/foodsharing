<template>
  <Container :title="$i18n('forum.threads')">
    <b-container>
      <b-row class="mt-2">
        <b-col
          cols="12"
          md="7"
          xl="8"
        >
          <ForumSearchField :group-id="groupId" :subforum-id="subforumId" />
        </b-col>
        <b-col
          cols="12"
          md="4"
          xl="3"
          class="m-2"
        >
          <b-button
            block
            variant="primary"
            :href="$url('forum', groupId, subforumId, null, null, true)"
          >
            {{ $i18n('forum.new_thread') }}
          </b-button>
        </b-col>
      </b-row>
    </b-container>
    <b-container>
      <ul class="forum_threads linklist">
        <div v-if="threads.totalRows > 0">
          <ThreadListEntry
            v-for="(thread, index) in threads.data"
            :key="index"
            :thread="thread"
          />
        </div>
        <li
          v-else
          slot="no-more"
          class="pl-2 thread-item"
        >
          <span v-if="!threads.totalRows">
            {{ $i18n('forum.no_threads') }}
          </span>
        </li>
        <b-pagination
          v-model="currentPage"
          :total-rows="threads.totalRows"
          :per-page="perPage"
          aria-controls="thread-list"
          class="mt-3 my-0"
          @input="loadThreads(currentPage)"
        />
      </ul>
    </b-container>
  </Container>
</template>

<script>
import ForumSearchField from './ForumSearchField'
import ThreadListEntry from './ThreadListEntry'
import Container from '@/components/Container/Container.vue'

import { listThreads } from '@/api/forum'
import { pulseError } from '@/script'

export default {
  components: { ForumSearchField, ThreadListEntry, Container },
  props: {
    groupId: { type: Number, required: true },
    subforumId: { type: Number, required: true },
  },
  data () {
    return {
      threads: [],
      currentPage: 1,
      perPage: 20,
    }
  },
  computed: {
    subforumName () {
      return this.subforumId === 1 ? 'botforum' : 'forum'
    },
  },
  async mounted () {
    await this.loadThreads(this.currentPage)
  },
  methods: {
    async loadThreads (currentPage) {
      const offset = (currentPage - 1) * this.perPage
      try {
        this.threads = (await listThreads(this.groupId, this.subforumId, offset)).object
      } catch {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
  },
}
</script>

<style lang="scss" scoped>
</style>
