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
        <div v-if="isLoading">
          <li
            v-for="i in perPage"
            :key="`skeleton-${i}`"
            class="pl-2 thread-item"
          >
            <div class="d-flex align-items-center py-2">
              <b-skeleton
                type="avatar"
                size="50px"
                class="mr-3"
              />
              <div class="flex-grow-1">
                <b-skeleton
                  width="75%"
                  height="1.1em"
                  class="mb-2"
                />
                <b-skeleton
                  width="50%"
                  height="0.9em"
                />
              </div>
              <div class="ml-auto">
                <b-skeleton
                  width="120px"
                  height="0.9em"
                  class="mb-1"
                />
                <b-skeleton
                  width="100px"
                  height="0.8em"
                />
              </div>
            </div>
          </li>
        </div>
        <div v-else-if="!searchActive && threads.totalCount > 0">
          <ThreadListEntry
            v-for="(thread, index) in threads.entries"
            :key="index"
            :thread="thread"
            :region-id="groupId"
            :subforum-id="subforumId"
          />
        </div>
        <li
          v-else
          slot="no-more"
          class="pl-2 thread-item"
        >
          <span v-if="!threads.totalCount">
            {{ $t('forum.no_threads') }}
          </span>
        </li>
        <b-pagination
          v-if="!searchActive"
          v-model="currentPage"
          :total-rows="threads.totalCount"
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
      threads: {},
      currentPage: 1,
      perPage: 20,
      isActiveFollower: false,
      searchActive: false,
      isLoading: false,
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
      this.isLoading = true
      try {
        this.threads = await listThreads(this.groupId, this.subforumId, offset)
      } catch {
        pulseError(this.$t('error_unexpected'))
      } finally {
        this.isLoading = false
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
