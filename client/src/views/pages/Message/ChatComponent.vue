<template>
  <vue-advanced-chat
    :current-user-id="String(userStore.getUserId)"
    :room-id="String(roomId)"
    :rooms="JSON.stringify(getRooms)"
    :loading-rooms="loadingRooms"
    :rooms-loaded="roomsLoaded"
    :messages="JSON.stringify(getMessages)"
    :messages-loaded="messagesLoaded"
    show-audio="false"
    show-emojis="true"
    show-reaction-emojis="false"
    emojis-suggestion-enabled="true"
    show-files="false"
    user-tags-enabled="false"
    :load-first-room="String(roomId !== null)"
    :single-room="popupMode"
    :text-messages="JSON.stringify(textMessages)"
    :theme="themeStore.isDark ? 'dark' : 'light'"
    :styles="JSON.stringify(computedStyle)"
    emoji-data-source="/assets/emoji-picker-element-data/de/data.json"
    @fetch-messages="fetchMessages($event.detail[0])"
    @fetch-more-rooms="fetchMoreRooms"
    @send-message="sendMessage($event.detail[0])"
    @open-failed-message="clickFailedMessage($event.detail[0])"
    @add-room="clickAddConversation"
  >
    <div slot="room-header-info">
      <ChatTitleComponent :conversation-id="isNewConversation ? null : roomId" />
    </div>

    <div
      v-for="conv in getConversations"
      :key="'room-list-avatar_' + conv.id"
      :slot="'room-list-avatar_' + conv.id"
      class="mr-2"
    >
      <ConversationAvatar :conversation="conv" />
    </div>

    <div slot="messages-empty">
      <SelectUsersComponent
        v-if="isNewConversation"
        id="select-users"
        ref="select-users"
        :select-users="newConversationSelectedUsers"
        @selected-users-changed="newConversationSelectedUsersChanged"
      />
      <span v-else>{{ textMessages.MESSAGES_EMPTY }}</span>
    </div>

    <div
      v-for="msg in getMessages"
      :key="'message-avatar_' + msg._id"
      :slot="'message-avatar_' + msg._id"
      style="height: 100%; align-self: flex-end;"
    >
      <Avatar :user="getUser(msg.senderId)" />
    </div>
    <PushNotificationModal v-if="askForPushNotifications" ref="pushModal" />
  </vue-advanced-chat>
</template>

<script>
import { register } from 'vue-advanced-chat'

import Avatar from '@/components/Avatar/Avatar.vue'
import ConversationAvatar from '@/components/Avatar/ConversationAvatar'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'
import Storage from '@/storage'

// Stores
import conversationStore from '@/stores/conversations'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { useThemeStore } from '@/stores/theme'
import SelectUsersComponent from './SelectUsersComponent.vue'
import ChatTitleComponent from './ChatTitleComponent.vue'
import PushNotificationModal from './PushNotificationModal.vue'

register()

const userStore = useUserStore()
const themeStore = useThemeStore()

// https://github.com/optidatacloud/vue-advanced-chat/blob/master/src/themes/index.js
const customStyle = {
  dark: {
    message: {
      backgroundMe: '#0f1d06',
    },
  },
  light: {

  },
}

const NEW_CONVERSATION_ID = Number.MAX_SAFE_INTEGER

export default {
  components: {
    Avatar,
    ConversationAvatar,
    SelectUsersComponent,
    ChatTitleComponent,
    PushNotificationModal,
  },
  props: {
    chatId: {
      type: Number,
      default: null,
    },
    // If this component is used as popup windows.
    // So hide conversation list, hide header, adopt sizing, ...
    popupMode: {
      type: Boolean,
      default: false,
    },
    askForPushNotifications: { type: Boolean, default: false },
  },
  setup () {
    return {
      themeStore,
    }
  },
  data () {
    return {
      userStore,
      defaultAvatar: '/img/mini_q_avatar.png',
      loadingRooms: true, // can be used to show/hide a spinner icon while rooms are loading the first time. Fetch more rooms don't need this boolean afterwards.

      roomId: this.chatId,
      roomChanging: true, // This must be set to inform the chat component about changing messages.
      newConversation: false, // true if a new conversation is currently starting. This displayes the selection of users for this conversation.
      newConversationSelectedUsers: [], // an array of users with attributes id and value (name)
      textMessages: {
        ROOMS_EMPTY: i18n('chat.empty'),
        ROOM_EMPTY: i18n('chat.no_conversation'),
        NEW_MESSAGES: i18n('chat.new_messages'),
        MESSAGES_EMPTY: i18n('chat.no_messages'),
        CONVERSATION_STARTED: i18n('chat.start_of_conversation'),
        TYPE_MESSAGE: i18n('chat.placeholder'),
        SEARCH: i18n('chat.search'),
        // The following messages could also be translated in the future when these features are implemented.
        // MESSAGE_DELETED: i18n('This message was deleted'),
        // IS_ONLINE: i18n('is online'),
        // LAST_SEEN: i18n('last seen '),
        // IS_TYPING: i18n('is writing...'),
        // CANCEL_SELECT_MESSAGE: i18n('Cancel'),
      },
    }
  },
  computed: {
    isNewConversation () {
      return this.roomId === NEW_CONVERSATION_ID
    },
    getConversations () {
      return conversationStore.conversations
    },
    getRooms () {
      const rooms = this.convertRooms(conversationStore.conversations)
      return rooms
    },
    roomsLoaded () {
      return !conversationStore.hasMoreConversations
    },
    getMessages () {
      if (this.roomChanging) { return [] }
      const conversation = conversationStore.conversations[this.roomId]
      if (!conversation) {
        return [] // conversation has not been loaded, will be done in the background
      }
      const messages = this.convertMessages(conversation)
      return messages
    },
    messagesLoaded () {
      if (this.roomChanging) { return false }
      if (this.roomId === NEW_CONVERSATION_ID) { return true }
      return !conversationStore.conversations[this.roomId]?.hasMoreMessages
    },
    computedStyle () {
      if (themeStore.isDark) {
        return customStyle.dark
      } else {
        return customStyle.light
      }
    },
  },
  watch: {
    async chatId (newChatId, oldChatId) {
      await conversationStore.getConversation(newChatId)
      this.roomId = newChatId
    },
  },
  async created () {
    this.storage = new Storage('chat-text-')
    await this.loadRooms()
  },
  async mounted () {
    if (this.askForPushNotifications) {
      this.$refs.pushModal.maybeShow()
    }
    this.registerMessageTextEvents()

    // Using global css is not possible anymore in web components
    const style = document.createElement('style')

    if (this.popupMode) {
      style.innerHTML = `
      .vac-card-window {
        height: 400px !important;
        box-shadow: unset !important;
      }

      .vac-room-header {
        display: none !important;
      }

      .vac-col-messages .vac-container-scroll {
        margin-top: 0 !important;
      }

      .vac-message-wrapper .vac-message-container {
        padding: 2px 5px 0px 5px !important;
      }

      .vac-message-wrapper .vac-offset-current {
        margin-left: 0% !important;
      }

      .vac-message-wrapper .vac-message-box {
        max-width: 100% !important;
      }

      #roomTextarea {
        max-height: 120px;
      }

      .vac-icon-textarea svg {
        margin: 0 3px !important;
      }

      .vac-box-footer {
        padding: 5px 4px !important;
      }
      `
    } else {
      style.innerHTML = `
      .vac-card-window {
        height: 100% !important;
      }

      .vac-message-wrapper .vac-offset-current {
        margin-left: 20% !important;
      }

      .vac-message-wrapper .vac-message-box {
        max-width: 80% !important;
      }

      @media (min-width: 576px) and (min-height: 576px)
      {
        .vac-card-window {
          height: calc(100% - 3px) !important; /* required to see shadow around chat component */
        }
      }
      `
    }
    style.innerHTML += `
    #roomTextarea {
      font-size: 12px;
    }

    .vac-room-list .vac-text-last .vac-text-ellipsis,
    .vac-room-list .vac-room-name {
      -webkit-line-clamp: 2;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      overflow: hidden;
      white-space: initial;
    }

    .vac-message-wrapper .vac-message-container {
      padding-bottom: 0px !important;
    }

    .vac-message-wrapper {
      margin-top: 5px;
    }

    .vac-container-scroll,
    #roomTextarea,
    .vac-room-list {
      /* Don't scroll main page when cursor is in chat window */
      overscroll-behavior: contain;
    }

    .vac-message-wrapper .vac-format-message-wrapper {
      font-size: 12px;
    }

    .vac-room-list .vac-text-last .vac-text-ellipsis {
      /* When last message contains a link, the text is splitted into multiple spans. So limit max text area to 2 lines. https://github.com/antoine92190/vue-advanced-chat/issues/408 */
      overflow: hidden;
      line-height: 19px;
      max-height: calc(2 * 19px);
    }
    `
    this.$el.shadowRoot.appendChild(style)
  },
  methods: {
    openChat (chatId) {
      this.roomId = chatId
    },
    getMessageTextComponent () {
      return this.$el.shadowRoot.querySelector('#roomTextarea')
    },
    registerMessageTextEvents () {
      setTimeout(() => {
        // This timeout is required so that the chat component has initialized completely.

        this.getMessageTextComponent().addEventListener('click', async () => {
          await conversationStore.setReadStatus(this.roomId, true)
        })

        this.getMessageTextComponent().addEventListener('input', async () => {
          if (this.getMessageTextComponent().value !== '') {
            this.storage.set(this.roomId, this.getMessageTextComponent().value)
          } else {
            this.storage.del(this.roomId)
          }
        })

        if (this.popupMode) {
          this.getMessageTextComponent().focus()
        }
      }, 1)
    },
    /**
     * This is triggered every time a room is opened. If the room is opened for the first time, the options param will hold reset: true.
     * This will also be triggered if the user has scrolled to top to load more messages.
     */
    async fetchMessages ({ room, options }) {
      if (room === undefined) return
      if (room.roomId === undefined) return
      const roomId = Number(room.roomId)

      if (options?.reset) {
        this.roomChanging = true
        this.roomId = roomId
        await conversationStore.setReadStatus(roomId, true)
        const storedChatText = this.storage.get(this.roomId)
        if (storedChatText) {
          this.setMessageText(storedChatText)
        }
      }

      if (roomId !== NEW_CONVERSATION_ID) {
        const conversation = await conversationStore.getConversation(roomId)
        if ((options?.reset && Object.keys(conversation.messages).length <= 1) || (!options?.reset)) {
          // Load only more messages when no messages have been loaded when opening the chat (Or a maximum of one message which was send when creating a new conversation).
          // or when the user scrolls up, so options.reset will be undefined
          if (conversation.hasMoreMessages) {
            await conversationStore.loadMoreMessages(roomId) // this will update conversation variable
          }
        }
      }

      setTimeout(() => {
        // This timeout is required so that the chat component works correctly.
        this.roomChanging = false
      }, 100)
    },
    getRoomName (conversation) {
      if (conversation.title) { return conversation.title }
      return conversation.members
        .filter(m => m !== userStore.getUserId)
        .map(m => {
          if (ProfileStore.profiles[m]) {
            return ProfileStore.profiles[m].name
          } else {
            return this.$i18n('chat.unknown_username')
          }
        })
        .join(', ')
    },
    async loadRooms () {
      await conversationStore.initConversations()
      this.loadingRooms = false // turn off loading spinner if rooms finished
    },
    convertMessages (conversation) {
      const chatMessages = []
      for (const message of Object.values(conversation.messages)) {
        let username = this.$i18n('chat.unknown_username')
        if (ProfileStore.profiles[message.authorId]) {
          username = ProfileStore.profiles[message.authorId].name
        }

        const chatMessage = {
          _id: message.id,
          indexId: message.id,
          content: message.body,
          senderId: String(message.authorId),
          username: username,
          date: this.$dateFormatter.date(message.sentAt),
          timestamp: this.$dateFormatter.time(message.sentAt),
          system: false,
          // saved: !message.failure, // can be activated when 'distributed' is also implemented in backend. Will otherwise confuse users when only 1 check is displayed.
          distributed: false,
          seen: userStore.getUserId !== message.authorId, // Setting the other users seen, will hide "New Messages" indicator in chat. TODO: https://gitlab.com/foodsharing-dev/foodsharing/-/issues/1484
          deleted: false,
          failure: message.failure,
          disableActions: true,
          disableReactions: true,
        }
        chatMessages.push(chatMessage)
      }
      return chatMessages
    },
    newConversationRoom () {
      const room = {
        roomId: String(NEW_CONVERSATION_ID),
        roomName: i18n('chat.new_message'),
        avatar: null,
        unreadCount: 0,
        index: Number.MAX_SAFE_INTEGER, // dispay at top of room list
      }

      room.users = []
      const user = {
        _id: userStore.getUserId,
        username: ProfileStore.profiles[userStore.getUserId].name,
        avatar: ProfileStore.profiles[userStore.getUserId].avatar,
        status: {
        },
      }
      room.users.push(user)
      return room
    },
    convertRooms (conversations) {
      const convs = Object.values(conversations)

      const rooms = []
      for (const conv of convs) {
        let room = {
          roomId: String(conv.id),
          roomName: this.getRoomName(conv),
          avatar: null,
          unreadCount: Number(conv.unreadMessages),
          index: Number.MAX_SAFE_INTEGER - 1, // order at top of room list, but after new conversation entry
        }

        if (conv.lastMessage) {
          let username = this.$i18n('chat.unknown_username')
          let senderId = this.$i18n('chat.unknown_username')
          if (conv.lastMessage.authorId && ProfileStore.profiles[conv.lastMessage.authorId]) {
            username = ProfileStore.profiles[conv.lastMessage.authorId].name
            senderId = String(conv.lastMessage.authorId)
          }

          room = {
            ...room,
            avatar: null,
            index: conv.lastMessage.sentAt.getTime(), // use unix timestamp
            lastMessage: {
              content: conv.lastMessage.body,
              senderId: senderId,
              username: username,
              timestamp: this.$dateFormatter.relativeTime(conv.lastMessage.sentAt, { short: true }),
              // saved: true, // can be activated when 'distributed' is also implemented in backend. Will otherwise confuse users when only 1 check is displayed.
              distributed: false,
              seen: false,
              new: conv.unreadMessages,
            },
          }
        }

        room.users = []
        for (const userId of conv.members) {
          const profile = ProfileStore.profiles[userId]
          const user = {
            _id: userId,
            username: profile && profile.name ? profile.name : this.$i18n('chat.unknown_username'),
            avatar: profile && profile.avatar ? profile.avatar : this.defaultAvatar,
            status: {
              // The following properties could also be used in the vue-advanced-chat component when these are implemented in the backend.
              // state: 'offline',
              // lastChanged: 'today, 14:30',
            },
          }
          room.users.push(user)
        }
        rooms.push(room)
      }
      if (this.newConversation) {
        rooms.push(this.newConversationRoom())
      }
      return rooms
    },
    fetchMoreRooms () {
      conversationStore.loadConversations()
    },
    setMessageText (text) {
      setTimeout(() => {
        // This timeout is required so that the message text is changed after the chat component has changed the values
        this.getMessageTextComponent().value = text
        this.getMessageTextComponent().dispatchEvent(new Event('input')) // trigger component updates
      }, 0)
    },
    async sendMessage ({ content, roomId, files, replyMessage }) {
      if (this.roomId === NEW_CONVERSATION_ID) {
        if (this.newConversationSelectedUsers.length === 0) {
          this.setMessageText(content) // keep current message text
          pulseError(i18n('chat.empty_recipients'))
          return
        }

        try {
          const newConversationId = await conversationStore.createConversation(this.newConversationSelectedUsers.map(user => user.id))
          try {
            await conversationStore.sendMessage(newConversationId, content)
            setTimeout(() => {
              // This timeout is required so that the chat component has updated its room list
              this.newConversation = false
              this.roomId = newConversationId
            }, 0)
            this.newConversationSelectedUsers = [] // clear selected users
          } catch (e) {
            pulseError(i18n('chat.error_sending_message'))
            console.error(e)
            return
          }
        } catch (e) {
          this.setMessageText(content) // keep current message text
          return
        }
      } else {
        await conversationStore.sendMessage(roomId, content)
      }
      await conversationStore.setReadStatus(this.roomId, true)
      this.storage.del(this.roomId)
    },
    /**
     * Will be called when clicked on the failure icon next to a message
     */
    async clickFailedMessage ({ roomId, message }) {
      console.error('clickFailedMessage', roomId, message)
      await conversationStore.resendFailedMessage(roomId, message.indexId)
    },
    /**
     * Will be called if the plus button was pressed next to the search bar
     */
    clickAddConversation () {
      this.newConversation = true
      setTimeout(() => {
        // This timeout is required so that the chat component has updated its room list
        this.roomId = NEW_CONVERSATION_ID
      }, 0)
    },
    newConversationSelectedUsersChanged (users) {
      this.newConversationSelectedUsers = users
    },
    getUser (authorId) {
      return ProfileStore.profiles[authorId]
    },
  },
}
</script>

<style lang="scss">
vue-advanced-chat {
  #select-users {

    position: absolute; // Otherwise the message scroll area has flickering at the bottom of the area when opening and closing user selection.
    width: calc(100% - 10px); // vac-messages-container has 5px padding, so remove 2*padding of width.

    input {
      min-width: unset;
    }
  }
}
</style>
