<template>
  <Container
    :title="$t('forum.threads')"
    :collapsible="false"
  >
    <template #options>
      <b-form-checkbox
        v-model="isActiveFollower"
        switch
        class="m-2"
        @change="setActiveFollowership(isActiveFollower)"
      >
        <i class="fas fa-fw" :class="isActiveFollower ? 'fa-bell' : 'fa-bell-slash'" />
        {{ $t('forum.options.bells_for_new_posts') }}
      </b-form-checkbox>
    </template>
    <b-container>
      <b-row class="mt-2">
        <b-col
          cols="12"
          md="7"
          xl="8"
        >
          <ForumSearchField
            :group-id="groupId"
            :subforum-id="subforumId"
            @search-active="setSearchActive"
          />
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
            class="btn-sm"
            :href="$url('forum', groupId, subforumId, null, null, true)"
          >
            {{ $t('forum.new_thread') }}
          </b-button>
        </b-col>
      </b-row>
    </b-container>
    <b-container>
      <ul class="forum_threads linklist">
        <div v-if="!searchActive && threads.totalRows > 0">
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
            {{ $t('forum.no_threads') }}
          </span>
        </li>
        <b-pagination
          v-if="!searchActive"
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

import { getForumFollowing, listThreads, setForumFollowing } from '@/api/forum'
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
      isActiveFollower: false,
      searchActive: false,
    }
  },
  computed: {
    subforumName () {
      return this.subforumId === 1 ? 'botforum' : 'forum'
    },
  },
  watch: {
    groupId () {
      this.update()
    },
    subforumId () {
      this.update()
    },
  },
  mounted () {
    this.update()
  },
  methods: {
    async update () {
      await this.loadThreads(this.currentPage)
      this.isActiveFollower = (await getForumFollowing(this.groupId)).isFollowing
    },
    async loadThreads (currentPage) {
      const offset = (currentPage - 1) * this.perPage
      try {
        this.threads = (await listThreads(this.groupId, this.subforumId, offset)).object
      } catch {
        pulseError(this.$t('error_unexpected'))
      }
    },
    setActiveFollowership (isActiveFollower) {
      setForumFollowing(this.groupId, isActiveFollower)
      this.isActiveFollower = isActiveFollower
    },
    setSearchActive (active) {
      this.searchActive = !!active
    },
  },
}
</script>

<style lang="scss" scoped>
</style>
