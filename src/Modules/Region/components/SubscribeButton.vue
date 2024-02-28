<template>
  <b-dropdown
    split
    block
    right
    :variant="isFollowing ? 'primary' : 'outline-secondary'"
    class="subscribe-btn my-2"
    @click="updateFollowing()"
  >
    <template #button-content>
      <span v-if="isFollowing">
        <i v-if="isFollowingBell" class="fas fa-bell mr-1" />
        <i
          v-if="isFollowingEmail"
          class="fas fa-envelope mr-1"
        />
        {{ $i18n('forum.thread.subscriptions.subscribed') }}
      </span>
      <span v-else>
        <i class="far fa-bell mr-1" /> {{ $i18n('forum.thread.subscriptions.subscribe') }}
      </span>
    </template>
    <b-dropdown-text>{{ $i18n('forum.follow.header') }}</b-dropdown-text>
    <b-dropdown-divider />
    <b-dropdown-form>
      <b-form-checkbox
        switch
        class="bell-switch"
        :checked="isFollowingBell"
        @change="updateFollowBell"
      >
        <a :class="{ 'text-strike': !isFollowingBell }">{{ $i18n('forum.follow.bell') }}</a>
      </b-form-checkbox>
      <b-form-checkbox
        switch
        class="email-switch"
        :checked="isFollowingEmail"
        @change="updateFollowEmail"
      >
        <a :class="{ 'text-strike': !isFollowingEmail }">{{ $i18n('forum.follow.email') }}</a>
      </b-form-checkbox>
    </b-dropdown-form>
  </b-dropdown>
</template>

<script>
import * as api from '@/api/forum'
import { pulseError } from '@/script'

export default {
  props: {
    isFollowingBell: {
      type: Boolean,
      required: true,
    },
    isFollowingEmail: {
      type: Boolean,
      required: true,
    },
    threadId: {
      type: Number,
      required: true,
    },
  },
  computed: {
    isFollowing () {
      return this.isFollowingBell || this.isFollowingEmail
    },
  },
  methods: {
    async updateFollowBell () {
      const targetState = !this.isFollowingBell
      try {
        if (targetState) {
          await api.followThreadByBell(this.threadId)
        } else {
          await api.unfollowThreadByBell(this.threadId)
        }
        this.$emit('update:bell', targetState)
      } catch (err) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    async updateFollowEmail () {
      const targetState = !this.isFollowingEmail
      try {
        if (targetState) {
          await api.followThreadByEmail(this.threadId)
        } else {
          await api.unfollowThreadByEmail(this.threadId)
        }
        this.$emit('update:email', targetState)
      } catch (err) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    updateFollowing () {
      if (this.isFollowingEmail) {
        this.updateFollowEmail()
      }
      if (!this.isFollowingEmail || this.isFollowingBell) {
        this.updateFollowBell()
      }
    },
  },
}
</script>
