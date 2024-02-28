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
        <ThreadListEntry
          v-for="(el, index) in threads"
          :key="index"
          :thread="el"
        />

        <infinite-loading
          spinner="waveDots"
          @infinite="infiniteHandler"
        >
          <li slot="no-more" class="thread-item">
            <span v-if="!threads.length">
              {{ $i18n('forum.no_threads') }}
            </span>
          </li>
        </infinite-loading>
      </ul>
    </b-container>
  </Container>
</template>

<script>
import ForumSearchField from './ForumSearchField'
import ThreadListEntry from './ThreadListEntry'
import InfiniteLoading from 'vue-infinite-loading'
import Container from '@/components/Container/Container.vue'

import { listThreads } from '@/api/forum'

export default {
  components: { ForumSearchField, ThreadListEntry, InfiniteLoading, Container },
  props: {
    groupId: { type: Number, required: true },
    subforumId: { type: Number, required: true },
  },
  data () {
    return {
      threads: [],
      offset: 0,
    }
  },
  computed: {
    subforumName () {
      return this.subforumId === 1 ? 'botforum' : 'forum'
    },
  },
  methods: {
    async infiniteHandler ($state) {
      const threads = (await listThreads(this.groupId, this.subforumId, this.offset)).data
      if (threads.length) {
        this.offset += threads.length
        // sorting is awkward due to sticky threads
        // => it happens in the backend for now
        this.threads.push(...threads)
        $state.loaded()
      } else {
        $state.loaded()
        $state.complete()
      }
    },
  },
}
</script>

<style lang="scss" scoped>
</style>
