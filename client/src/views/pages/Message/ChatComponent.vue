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
    textarea-auto-focus="false"
    :load-first-room="String(roomId !== null)"
    :single-room="popupMode"
    :text-messages="JSON.stringify(textMessages)"
    :theme="themeStore.isDark ? 'dark' : 'light'"
    :styles="JSON.stringify(computedStyle)"
    emoji-data-source="/assets/emoji-picker-element-data/de/data.json"
    :message-actions="JSON.stringify(messageActions)"
    @fetch-messages="fetchMessages($event.detail[0])"
    @fetch-more-rooms="fetchMoreRooms"
    @send-message="sendMessage($event.detail[0])"
    @open-failed-message="clickFailedMessage($event.detail[0])"
    @add-room="clickAddConversation"
  >
    <template v-if="!popupMode">
      <div slot="room-header-info" class="room-header-info">
        <ChatTitleComponent :conversation-id="isNewConversation ? null : roomId" />
      </div>

      <div slot="room-options" class="room-options">
        <ChatUnreadIndicator
          class="room-unread-count"
          :unread="conversation?.unreadMessages"
        />
        <div>
          <OverflowMenu
            icon="ellipsis-v"
            variant="link"
            class="room-options-menu"
            :title="$t('options')"
            :float-right="false"
            :options="menuOptions"
          />
        </div>
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
    </template>

    <div
      v-for="msg in getMessages"
      :key="'message-avatar_' + msg._id"
      :slot="'message-avatar_' + msg._id"
      style="height: 100%; align-self: flex-end;"
    >
      <Avatar :user="getUser(msg.senderId)" />
    </div>

    <PushNotificationModal v-if="askForPushNotifications" ref="pushModal" />
    <RenameDialog ref="renameDialog" @save-rename="$emit('save-rename', $event)" />
    <ParticipantsDialog ref="participantsDialog" />
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
import OverflowMenu from '@/components/OverflowMenu.vue'
import ChatUnreadIndicator from '@/components/Chat/ChatUnreadIndicator.vue'
import RenameDialog from '@/components/Chat/RenameDialog.vue'
import ParticipantsDialog from '@/components/Chat/ParticipantsDialog.vue'

register()

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
    RenameDialog,
    ChatUnreadIndicator,
    ConversationAvatar,
    SelectUsersComponent,
    ChatTitleComponent,
    OverflowMenu,
    ParticipantsDialog,
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
    popupOpenedExplicitly: {
      type: Boolean,
      default: false,
    },
    askForPushNotifications: { type: Boolean, default: false },
  },
  setup () {
    const userStore = useUserStore()
    const themeStore = useThemeStore()
    return {
      userStore,
      themeStore,
    }
  },
  data () {
    return {
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
      messageActions: [{
        name: 'replyMessage',
        title: i18n('chat.reply'),
      }],
    }
  },
  computed: {
    conversation () {
      return conversationStore.conversations[this.roomId]
    },
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
      if (this.themeStore.isDark) {
        return customStyle.dark
      } else {
        return customStyle.light
      }
    },
    canRenameConversation () {
      return this.conversation && !this.conversation.storeId && this.conversation.id !== NEW_CONVERSATION_ID
    },
    menuOptions () {
      return [
        {
          textKey: 'chat.mark_as.unread',
          icon: 'eye-slash',
          hide: (this.conversation?.unreadMessages ?? 0) !== 0,
          callback: () => this.markMessagesAsUnread(),
        },
        {
          textKey: 'chat.rename',
          icon: 'edit',
          hide: !this.canRenameConversation,
          callback: () => this.showRenameDialog(),
        },
        {
          textKey: 'chat.show_participants',
          icon: 'users',
          hide: (this.conversation?.members?.length ?? 0) <= 3,
          callback: () => { this.showParticipantsDialog() },
        },
      ]
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

      .vac-message-actions-wrapper .vac-menu-left {
        right: -5px;
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

    .vac-room-badge {
      display: none;
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

    .markdown blockquote {
      margin: 0;
      padding: 0 0.5rem;
      border-left: 2px solid var(--fs-color-info-500);
      background-color: var(--fs-color-info-100);
      line-height: 1;
    }
    .markdown code {
      white-space: normal;
    }
    .markdown img {
      max-width: 100%;
    }
    .markdown h1, .markdown h2, .markdown h3, .markdown h4, .markdown h5, .markdown h6 {
      margin: 0;
    }
    .markdown ul {
      padding-left: 1em;
      margin: 0;
    }
    `
    this.$el.shadowRoot.appendChild(style)

    await this.$nextTick()
    this.registerMessageTextEvents()
    this.registerMessageListEvents()
    this.registerScrollEvents()
  },
  methods: {
    openChat (chatId) {
      this.roomId = chatId
    },
    getMessageTextComponent () {
      return this.$el.shadowRoot.querySelector('#roomTextarea')
    },
    getMessageListComponent () {
      return this.$el.shadowRoot.querySelector('#messages-list')
    },
    focusInput () {
      const messageTextInput = this.getMessageTextComponent()
      messageTextInput.focus()

      // Convince caret to be actually rendered on firefox:
      const len = messageTextInput.value ? messageTextInput.value.length : 0
      messageTextInput.setSelectionRange(len, len)
    },
    // Mark all messages in the current conversation as read, but only if the client
    // knows about unread messages. This avoids marking messages as read when
    // the client has not received them yet.
    async markMessagesAsRead () {
      if ((conversationStore.conversations[this.roomId]?.unreadMessages ?? 0) !== 0) {
        await conversationStore.setReadStatus(this.roomId, true)
      }
    },
    async markMessagesAsUnread () {
      if (conversationStore.conversations[this.roomId]?.unreadMessages === 0) {
        await conversationStore.setReadStatus(this.roomId, false)
      }
      document.activeElement.blur() // without this the entry is focused after clicking
    },
    isChatScrolledToBottom () {
      const messageList = this.getMessageListComponent()
      return messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight <= 2 // rounding tolerance
    },
    registerMessageTextEvents () {
      this.getMessageTextComponent().addEventListener('click', () => {
        this.markMessagesAsRead()
      })

      this.getMessageTextComponent().addEventListener('input', () => {
        if (this.getMessageTextComponent().value !== '') {
          this.storage.set(this.roomId, this.getMessageTextComponent().value)
        } else {
          this.storage.del(this.roomId)
        }
        this.markMessagesAsRead()
      })

      this.getMessageTextComponent().addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          // Hide the event from VAC, which wants to delete the current text.
          event.stopImmediatePropagation()
        }
      }, true)
    },
    registerMessageListEvents () {
      // Mark messages as read when the 'scroll to bottom' button is clicked
      this.$el.shadowRoot.querySelector('.vac-col-messages').addEventListener('click', (event) => {
        // Catch events on parent because the button only exists conditionally
        if (event.target.closest('.vac-icon-scroll') !== null) {
          this.markMessagesAsRead()
        }
      }, true)

      this.getMessageListComponent().addEventListener('click', () => {
        if (this.isChatScrolledToBottom()) {
          this.markMessagesAsRead()
        }
      })
    },
    registerScrollEvents () {
      // Detect user actions which will probably cause scrolling,
      // then mark messages as read when user scrolls to bottom after such an action.
      // This ignores VAC scrolling on page load or when new messages arrive.
      const messageList = this.getMessageListComponent()

      let lastWheelDownAction = null
      messageList.addEventListener('wheel', (event) => {
        if (event.deltaY > 0) { lastWheelDownAction = new Date() }
      })

      let lastKeyDownAction = null
      messageList.tabIndex = 0 // Make message list focusable to receive keydown events when focused
      messageList.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'PageDown', ' '].includes(event.key)) {
          lastKeyDownAction = new Date()
        }
      })

      let isDraggingScrollbar = false
      messageList.addEventListener('mousedown', ({ offsetX }) => {
        isDraggingScrollbar ||= offsetX < 0 || offsetX > messageList.clientWidth
      })
      messageList.addEventListener('mouseup', () => { isDraggingScrollbar = false })

      let isTouchDragging = false
      messageList.addEventListener('touchstart', () => { isTouchDragging = true })
      messageList.addEventListener('touchend', () => { isTouchDragging = false })

      let userScrollEnd = null
      const startOrProlongUserScroll = () => { userScrollEnd = new Date(new Date().getTime() + 500) }
      const userScrollActive = () => { return userScrollEnd && new Date() < userScrollEnd }

      let wasScrolledUp = false
      messageList.addEventListener('scroll', () => {
        if (userScrollActive()) {
          startOrProlongUserScroll()
        } else {
          const recentWheel = lastWheelDownAction && (new Date() - lastWheelDownAction) < 500
          const recentKeydown = lastKeyDownAction && (new Date() - lastKeyDownAction) < 500
          const scrollMayBeUserInitiated = recentWheel || recentKeydown || isTouchDragging || isDraggingScrollbar
          if (scrollMayBeUserInitiated) { startOrProlongUserScroll() }
        }

        const scrolledToBottom = this.isChatScrolledToBottom()
        if (userScrollActive() && wasScrolledUp && scrolledToBottom) {
          this.markMessagesAsRead()
        }
        wasScrolledUp = !scrolledToBottom
      })
    },
    handleUnMinimized () {
      if (this.isChatScrolledToBottom()) {
        this.markMessagesAsRead()
      }
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
        const storedChatText = this.storage.get(this.roomId)
        if (storedChatText) {
          this.setMessageText(storedChatText)
        }
      }

      const isNewConversation = roomId === NEW_CONVERSATION_ID
      if (!isNewConversation) {
        const markAsRead = !!options?.reset && (this.popupMode ? this.popupOpenedExplicitly : true)
        const conversation = await conversationStore.getConversation(roomId, markAsRead)
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

        if (this.popupMode ? this.popupOpenedExplicitly : (options?.reset && !isNewConversation)) {
          this.focusInput()
        }
      }, 100)
    },
    getRoomName (conversation) {
      if (conversation.title) { return conversation.title }
      return conversation.members
        .filter(m => m !== this.userStore.getUserId)
        .map(m => ProfileStore.profiles[m]?.name ?? this.$t('chat.unknown_username'))
        .join(', ')
    },
    async loadRooms () {
      await conversationStore.initConversations()
      this.loadingRooms = false // turn off loading spinner if rooms finished
    },
    convertMessages (conversation) {
      const chatMessages = []
      for (const message of Object.values(conversation.messages)) {
        const chatMessage = {
          _id: message.id,
          indexId: message.id,
          content: message.body,
          senderId: String(message.authorId),
          username: ProfileStore.profiles[message.authorId]?.name ?? this.$t('chat.unknown_username'),
          date: this.$dateFormatter.date(message.sentAt),
          timestamp: this.$dateFormatter.time(message.sentAt),
          system: false,
          // saved: !message.failure, // can be activated when 'distributed' is also implemented in backend. Will otherwise confuse users when only 1 check is displayed.
          distributed: false,
          seen: this.userStore.getUserId !== message.authorId, // Setting the other users seen, will hide "New Messages" indicator in chat. TODO: https://gitlab.com/foodsharing-dev/foodsharing/-/issues/1484
          deleted: false,
          failure: message.failure,
          disableActions: false,
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
        _id: this.userStore.getUserId,
        username: ProfileStore.profiles[this.userStore.getUserId].name,
        avatar: ProfileStore.profiles[this.userStore.getUserId].avatar,
        status: {
        },
      }
      room.users.push(user)
      return room
    },
    showRenameDialog () {
      this.$refs.renameDialog?.open(this.conversation.id)
    },
    showParticipantsDialog () {
      this.$refs.participantsDialog?.open(this.conversation.id)
    },
    convertRooms (conversations) {
      const convs = Object.values(conversations)

      const rooms = []
      for (const conv of convs) {
        let room = {
          roomId: String(conv.id),
          roomName: this.getRoomName(conv),
          avatar: null,
          unreadCount: Number(conv.unreadMessages) > 0 ? Number(conv.unreadMessages) : 0,
          index: Number.MAX_SAFE_INTEGER - 1, // order at top of room list, but after new conversation entry
        }

        if (conv.lastMessage) {
          const lastAuthorId = conv.lastMessage.authorId
          room = {
            ...room,
            avatar: null,
            index: conv.lastMessage.sentAt.getTime(), // use unix timestamp
            lastMessage: {
              content: conv.lastMessage.body,
              senderId: String(lastAuthorId ?? ''),
              username: ProfileStore.profiles[lastAuthorId]?.name ?? this.$t('chat.unknown_username'),
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
            username: profile?.name ?? this.$t('chat.unknown_username'),
            avatar: profile?.avatar ?? this.defaultAvatar,
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
      if (replyMessage) {
        // Each line of the original message is prefaced by a '> '
        const citation = replyMessage.content.split('\n').map(line => '> ' + line).join('\n')
        content = citation + '\n\n' + content
      }
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
            pulseError(i18n('chat.error.sending_message'))
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
      this.markMessagesAsRead()
      this.storage.del(this.roomId)
    },
    /**
     * Will be called when clicked on the failure icon next to a message
     */
    async clickFailedMessage ({ roomId, message }) {
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

<style lang="scss" scoped>
vue-advanced-chat {
  #select-users {
    position: absolute; // Otherwise the message scroll area has flickering at the bottom of the area when opening and closing user selection.
    width: calc(100% - 10px); // vac-messages-container has 5px padding, so remove 2*padding of width.

    ::v-deep input {
      min-width: unset;
    }
  }
}

.room-options {
  display: flex;
  flex-direction: row;
  align-items: center;
  font-size: 1.1em;
}

@media only screen and (min-width: 768px) {
  // adjust spacing of badge/menu, VAC header padding differs on width
  .room-unread-count {
    margin-right: 6px;
  }
}

.room-options-menu ::v-deep .btn {
  margin-right: -0.25em;
  margin-left: -0.35em;
  font-size: 1.3em;
}
</style>
