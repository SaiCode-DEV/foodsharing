import Vue from 'vue'
import * as api from '@/api/conversations'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { goTo } from '@/script'
import { urls } from '@/helper/urls'
import { BROADCAST_TYPE, storeSynchronizer } from '@/broadcastChannel'

const REQUEST_LIMIT_CONVERSATIONS = 20
const REQUEST_LIMIT_MESSAGES = 25
const MARKED_AS_UNREAD = -1
export { MARKED_AS_UNREAD }

export default new Vue({
  data: {
    hasMoreConversations: true, // if all conversations have been loaded
    conversations: {},
    failureMessageId: -1, // unique message id for failed message sending. Always negative
    messagePageOpenChatListener: null, // the message page can register a listener to be called when a chat should be opened
    messagePopupOpenChatListener: null, // On the Desktop page, the popup chat can register a listener to be called when a chat should be opened
  },
  computed: {
    unreadCount () {
      return Object.values(this.conversations).filter(b => b.unreadMessages).length
    },
  },
  ...storeSynchronizer(BROADCAST_TYPE.UPDATE_CONVERSATIONS, 'conversations'),
  methods: {

    /**
     * This function can be called to load the conversations for the first time.
     * When there are already conversations loaded, don't fetch them again.
     */
    async initConversations (limit = REQUEST_LIMIT_CONVERSATIONS) {
      const conversationCount = Object.values(this.conversations).length
      if (conversationCount === 0) {
        await this.loadConversations(limit)
      }
    },

    /**
     * This function will read the next conversations by a limit.
     * This function can be called multiple times and if no more conversations are available,
     * then hasMoreConversations will be set to false
     * @param {int} limit Limit the amount of conversations per call
     */
    async loadConversations (limit = REQUEST_LIMIT_CONVERSATIONS) {
      const offset = Object.values(this.conversations).length
      const response = await api.getConversationList(limit, offset)
      ProfileStore.updateFrom(response.profiles)
      this.hasMoreConversations = response.conversations.length === limit
      for (const conversation of response.conversations) {
        this.assignConversationToStore(conversation)
      }
    },
    /**
     * Get conversation from store if exists else load from api
     * @param Number conversationId Conversation ID
     */
    async getConversation (conversationId) {
      if (this.conversations[conversationId] === undefined) {
        await this.loadConversation(conversationId)
      }
      return this.conversations[conversationId]
    },
    assignConversationToStore (newConversation) {
      const storedConversation = this.conversations[newConversation.id] ?? { messages: {}, hasMoreMessages: true }
      for (const message of newConversation.messages || []) {
        storedConversation.messages[message.id] = convertMessage(message)
      }
      Vue.set(this.conversations, newConversation.id, {
        ...newConversation,
        messages: storedConversation.messages,
        lastMessage: convertMessage(newConversation.lastMessage),
        hasMoreMessages: storedConversation.hasMoreMessages,
      })
    },
    async loadConversation (conversationId) {
      /* always load conversation for proper read mark handling.
      * Will still cache messages during store lifetime */
      const response = await api.getConversation(conversationId)
      ProfileStore.updateFrom(response.profiles)
      this.assignConversationToStore(response.conversation)
    },
    async loadMoreMessages (conversationId) {
      const storedConversation = this.conversations[conversationId]
      const oldestMessageId = Object.keys(storedConversation.messages)[0]
      const response = await api.getMessages(conversationId, oldestMessageId, REQUEST_LIMIT_MESSAGES)
      ProfileStore.updateFrom(response.profiles)
      const newMessages = {}
      for (const message of response.messages) {
        newMessages[message.id] = convertMessage(message)
      }
      Vue.set(this.conversations[conversationId], 'messages', { ...storedConversation.messages, ...newMessages })
      Vue.set(this.conversations[conversationId], 'hasMoreMessages', response.messages.length === REQUEST_LIMIT_MESSAGES)
      return response.messages.length
    },
    /**
     * This function will be called when a new message over web socket has been received
     * @param Object data
     */
    async newMessageReceived (data) {
      const conversationId = data.cid
      if (!(conversationId in this.conversations)) {
        await this.loadConversation(conversationId)
        /* likely, when loading the conversation after the push message appeared, we don't need to add the push message.
        Still, I think it shouldn't harm...
         */
      }
      this.assignMessageToStore(conversationId, data.message)
    },
    async assignMessageToStore (conversationId, message) {
      Vue.set(this.conversations[conversationId].messages, message.id, convertMessage(message))
      Vue.set(this.conversations[conversationId], 'lastMessage', convertMessage(message))
      if (message.authorId !== useUserStore().getUserId) {
        this.conversations[conversationId].unreadMessages = Math.max(1, this.conversations[conversationId].unreadMessages + 1)
      }
    },

    async setReadStatus (conversationId, read) {
      if (conversationId in this.conversations) {
        Vue.set(this.conversations[conversationId], 'unreadMessages', read ? 0 : MARKED_AS_UNREAD)
        await api.setReadStatus(conversationId, read)
      }
    },
    async markUnreadMessagesAsRead () {
      for (const conversationId in this.conversations) {
        await this.setReadStatus(conversationId, true)
      }
    },
    async sendMessage (conversationId, messageText) {
      try {
        const response = await api.sendMessage(conversationId, messageText)
        this.assignMessageToStore(conversationId, response.message)
      } catch (e) {
        const errorMessage = {
          id: this.failureMessageId,
          body: messageText,
          sentAt: new Date(),
          authorId: useUserStore().getUserId,
          failure: true,
        }
        Vue.set(this.conversations[conversationId].messages, this.failureMessageId, errorMessage)
        this.failureMessageId--
        console.error('sendMessage error', e)
      }
    },
    /**
     * Create a new conversation
     * @param Number[] userIds The user IDs which should receive the message
     * @returns The new created conversation ID
     */
    async createConversation (userIds) {
      const response = await api.createConversation(userIds)
      ProfileStore.updateFrom(response.profiles)
      this.assignConversationToStore(response.conversation)
      const conversationId = response.conversation.id
      return conversationId
    },
    async openMultiChat (participantIds) {
      const uniqueParticipantIds = Array.from(new Set(participantIds)) // remove duplicates
      const conversationId = await this.createConversation(uniqueParticipantIds)
      this.openChat(conversationId)
    },
    async resendFailedMessage (conversationId, failureMessageId) {
      const message = this.conversations[conversationId].messages[failureMessageId]
      const response = await api.sendMessage(conversationId, message.body)
      this.assignMessageToStore(conversationId, response.message)
      Vue.delete(this.conversations[conversationId].messages, failureMessageId)
    },
    openChat (conversationId) {
      if (this.messagePageOpenChatListener) {
        this.messagePageOpenChatListener(conversationId)
      } else if (this.messagePopupOpenChatListener) {
        this.messagePopupOpenChatListener(conversationId)
      } else {
        goTo(urls.conversations(conversationId))
      }
    },
    async openChatWithUser (userId) {
      const conversation = await api.getConversationIdForConversationWithUser(userId)
      this.openChat(conversation.id)
    },
  },
})

export function convertMessage (val) {
  if (val !== null) {
    return {
      ...val,
      sentAt: new Date(val.sentAt),
    }
  } else {
    return null
  }
}
