<template>
  <button
    class="dropdown-header dropdown-item d-flex justify-content-between align-items-center"
    :class="{
      'list-group-item-warning': conversation.unreadMessages,
    }"
    @click="openChat"
  >
    <ConversationAvatar class="mr-2" :conversation="conversation" />
    <span class="d-flex w-100 flex-column text-truncate">
      <span class="d-flex justify-content-between align-items-center text-truncate">
        <span
          class="mb-1 text-truncate"
          v-text="title"
        />
        <Time
          class="font-weight-normal"
          :time="conversation.lastMessage.sentAt"
        />
      </span>
      <small class="position-relative">
        <span class="text-truncate d-inline-block w-100 text-preview">
          <strong>{{ lastAuthorName }}: </strong>
          {{ conversation.lastMessage.body }}
        </span>
        <i
          v-b-tooltip.noninteractive="$i18n(`chat.mark_as.${conversation.unreadMessages ? 'read' : 'unread'}`)"
          :class="`fas fa-eye${conversation.unreadMessages ? '' : '-slash'} mark-read-icon`"
          @click.stop="() => toggleReadStatus()"
        />
      </small>
    </span>
  </button>
</template>
<script>
import DataUser from '@/stores/user'
import profileStore from '@/stores/profiles'
import conversationStore from '@/stores/conversations'

import ConversationAvatar from '@/components/Avatar/ConversationAvatar'
import Time from '@/components/Time.vue'

export default {
  components: { ConversationAvatar, Time },
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
    lastAuthorName () {
      if (this.conversation.lastMessage.authorId === DataUser.getters.getUserId()) return this.$i18n('globals.you')
      return profileStore.profiles[this.conversation.lastMessage.authorId].name
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
.mark-read-icon {
  display: none;
}
.dropdown-item:hover {
  .text-preview {
    padding-right: 1.5rem;
  }
  .mark-read-icon {
    display: inline;
    position: absolute;
    right: 0;
    top: .35em;
    &:hover {
      color: var(--fs-color-info-500);
    }
  }
}
.nowrap {
    white-space: nowrap;
}
</style>
