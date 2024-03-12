<template>
  <button
    class="dropdown-header dropdown-item d-flex justify-content-between align-items-center"
    :class="{
      'list-group-item-warning': conversation.hasUnreadMessages,
    }"
    @click="openChat"
  >
    <ConversationAvatar
      class="mr-2"
      :unread="conversation.hasUnreadMessages"
      :conversation="conversation"
    />
    <span class="d-flex w-100 flex-column text-truncate">
      <span class="d-flex justify-content-between align-items-center text-truncate">
        <span
          class="mb-1 text-truncate"
          v-html="title"
        />
        <small class="text-muted text-right nowrap">
          {{ $dateFormatter.relativeTime(conversation.lastMessage.sentAt) }}
        </small>
      </span>
      <small
        class="text-truncate"
        v-html="conversation.lastMessage.body"
      />
    </span>
  </button>
</template>
<script>
import DataUser from '@/stores/user'
import profileStore from '@/stores/profiles'
import conversationStore from '@/stores/conversations'

import ConversationAvatar from '@/components/Avatar/ConversationAvatar'

export default {
  components: { ConversationAvatar },
  props: {
    conversation: { type: Object, default: () => ({}) },
  },
  computed: {
    title () {
      if (this.conversation.title) return this.conversation.title
      return this.filteredMemberList()
        .map(m => {
          if (profileStore.profiles[m]) {
            return profileStore.profiles[m].name
          } else {
            return this.$i18n('chat.unknown_username')
          }
        })
        .join(', ')
    },
    loggedinUser () {
      return DataUser.getters.getUser()
    },
  },
  methods: {
    openChat () {
      conversationStore.openChat(this.conversation.id)
    },
    filteredMemberList () {
      return this.conversation.members
        // without ourselve
        .filter(m => m !== this.loggedinUser.id)
    },
  },
}
</script>

<style lang="scss" scoped>

.nowrap {
    white-space: nowrap;
}
</style>
