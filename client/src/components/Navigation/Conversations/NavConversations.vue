<template>
  <Dropdown
    :title="$t('navigation.conversations')"
    icon="fa-comments"
    :badge="unread"
    direction="right"
    is-fixed-size
    :is-scrollable="conversations.length > 1"
  >
    <template
      v-if="conversations.length > 0"
      #content
    >
      <ConversationsEntry
        v-for="conversation in conversations"
        :key="conversation.id"
        :conversation="conversation"
      />
    </template>
    <template v-else #content>
      <small
        role="menuitem"
        class="disabled dropdown-item"
        v-text="$t('chat.empty')"
      />
    </template>
    <template #actions>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        :class="{ 'disabled': !unread }"
        @click="markUnreadMessagesAsRead"
      >
        <i class="icon-subnav fas fa-check-double" />
        {{ $t('menu.entry.mark_as_read') }}
      </button>
      <a
        :href="$url('conversations')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-comments" />
        {{ $t('menu.entry.all_messages') }}
      </a>
      <div class="dropdown-item">
        <b-form-checkbox
          v-if="mayUsePushNotifications"
          :checked="usePushNotifications"
          :disabled="pushNotificationsLoading"
          size="sm"
          switch
          @change="updatePushNotifications"
        >
          <span class="small" v-text="$t('settings.push.title')" />
        </b-form-checkbox>
      </div>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import conversationStore from '@/stores/conversations'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import ConversationsEntry from './NavConversationsEntry'
// Mixins
import PushNotificationMixin from '@/mixins/PushNotificationMixin.js'

export default {
  components: { ConversationsEntry, Dropdown },
  mixins: [PushNotificationMixin],
  computed: {
    conversations () {
      /* let res = Array.from(conversationStore.conversations) // .filter(c => c.lastMessage || c.messages)
      return res */
      return Object.values(conversationStore.conversations).filter((a) => (a.lastMessage != null)).sort(
        (a, b) => (!a.unreadMessages === !b.unreadMessages) ? (b.lastMessage.sentAt - a.lastMessage.sentAt) : (a.unreadMessages ? -1 : 1),
      )
    },
    unread () {
      if (conversationStore.unreadCount) {
        return conversationStore.unreadCount < 99 ? conversationStore.unreadCount : '99+'
      }
      return null
    },
  },
  methods: {
    markUnreadMessagesAsRead () {
      conversationStore.markUnreadMessagesAsRead()
    },
  },
}
</script>
