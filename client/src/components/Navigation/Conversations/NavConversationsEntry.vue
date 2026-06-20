<template>
  <button
    class="dropdown-header dropdown-item d-flex justify-content-between align-items-center gap"
    :class="{
      'list-group-item-warning': conversation.unreadMessages,
    }"
    @click="openChat"
  >
    <ConversationAvatar :conversation="conversation" />
    <span class="flex-grow-1 d-flex flex-column text-truncate">
      <span class="d-flex justify-content-between align-items-center text-truncate">
        <span
          class="mb-1 text-truncate"
          v-text="title"
        />
        <TimeDisplay
          class="font-weight-normal"
          :time="conversation.lastMessage.sentAt"
        />
      </span>
      <small class="position-relative">
        <span class="text-truncate d-inline-block w-100 text-preview">
          <strong>{{ lastAuthorName }}: </strong>
          {{ conversation.lastMessage.body }}
        </span>
      </small>
    </span>
    <b-button
      v-b-tooltip.noninteractive="$t(`chat.mark_as.${conversation.unreadMessages ? 'read' : 'unread'}`)"
      size="sm"
      variant="outline-secondary"
      class="mark-read-button"
      @click.stop="() => toggleReadStatus()"
    >
      <i
        :class="`fas fa-eye${conversation.unreadMessages ? '' : '-slash'}`"
      />
    </b-button>
  </button>
</template>
<script>
import { useUserStore } from '@/stores/user'
import profileStore from '@/stores/profiles'
import conversationStore from '@/stores/conversations'

import ConversationAvatar from '@/components/Avatar/ConversationAvatar'
import TimeDisplay from '@/components/TimeDisplay.vue'

export default {
  components: { ConversationAvatar, TimeDisplay },
  props: {
    conversation: { type: Object, default: () => ({}) },
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    title () {
      if (this.conversation.title) return this.conversation.title
      return this.filteredMemberList()
        .map(m => profileStore.profiles[m]?.name ?? this.$t('chat.unknown_username'))
        .join(', ')
    },
    loggedinUser () {
      return this.userStore.getUser
    },
    lastAuthorName () {
      const lastAuthorId = this.conversation.lastMessage?.authorId
      if (lastAuthorId === this.userStore.getUserId) return this.$t('globals.you')
      return profileStore.profiles[lastAuthorId]?.name ?? this.$t('chat.unknown_username')
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
    async toggleReadStatus () {
      await conversationStore.setReadStatus(this.conversation.id, !!this.conversation.unreadMessages)
      document.activeElement.blur() // without this the entry is focused after clicking
    },
  },
}
</script>

<style lang="scss" scoped>
.gap {
  gap: 0.5rem;
}

.mark-read-button {
  display: none;
}

.dropdown-item:hover .mark-read-button {
  display: inline;
}

@media (max-width: 768px) {
  .mark-read-button {
    display: inline;
  }
}
</style>
