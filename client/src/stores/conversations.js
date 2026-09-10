import Vue from 'vue'
import * as api from '@/api/conversations'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { navigate } from '@/helper/router'
import { pulseError } from '@/script'
import { urls } from '@/helper/urls'
import i18n from '@/helper/i18n'
import { BROADCAST_TYPE, storeSynchronizer } from '@/broadcastChannel'

const REQUEST_LIMIT_CONVERSATIONS = 20
const REQUEST_LIMIT_MESSAGES = 25
const MARKED_AS_UNREAD = -1
export { MARKED_AS_UNREAD }

// Generate the message idempotency key: 12 random hex chars, unique per sender and
// conversation within the retry window. crypto.getRandomValues also works outside
// secure contexts (plain http, e.g. the CI environment).
function generateClientKey () {
  const bytes = new Uint8Array(6)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('')
}

export default new Vue({
  data: {
    hasMoreConversations: true, // if all conversations have been loaded
    conversations: {},
    // How many conversations of the list have been fetched so far. Counted here
    // instead of from `conversations`, which also holds conversations that were
    // opened by id and are not part of the list yet.
    loadedFromList: 0,
    // The messages page and the header both ask for the list. Without this they
    // fetch the same page twice and the offset moves on by twice as much.
    conversationRequest: null,
    // pending preface texts for conversations opened programmatically
    pendingPreface: {},
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
      if (this.loadedFromList === 0) {
        await this.loadNextConversations(limit)
      }
    },

    /**
     * Load the next page, unless one is already on its way. Callers that arrive
     * while a request is running wait for it instead of starting a second one.
     */
    async loadNextConversations (limit = REQUEST_LIMIT_CONVERSATIONS) {
      if (!this.conversationRequest) {
        this.conversationRequest = this.loadConversations(limit)
          .finally(() => { this.conversationRequest = null })
      }

      return this.conversationRequest
    },

    async refreshConversations (limit = REQUEST_LIMIT_CONVERSATIONS) {
      limit = Math.max(limit, this.loadedFromList)
      if (limit === 0) {
        return // no conversations loaded yet
      }

      const response = await api.getConversationList(limit, 0)
      ProfileStore.updateFrom(response.profiles)
      this.loadedFromList = response.conversations.length
      this.hasMoreConversations = response.conversations.length === limit

      for (const conversation of response.conversations) {
        this.assignConversationToStore(conversation)
      }
    },

    /**
     * This function will read the next conversations by a limit.
     * This function can be called multiple times and if no more conversations are available,
     * then hasMoreConversations will be set to false
     * @param {int} limit Limit the amount of conversations per call
     */
    async loadConversations (limit = REQUEST_LIMIT_CONVERSATIONS) {
      const response = await api.getConversationList(limit, this.loadedFromList)
      ProfileStore.updateFrom(response.profiles)
      this.loadedFromList += response.conversations.length
      this.hasMoreConversations = response.conversations.length === limit
      for (const conversation of response.conversations) {
        this.assignConversationToStore(conversation)
      }
    },
    /**
     * Get conversation from store if exists else load from api
     */
    async getConversation (conversationId, markAsRead = false) {
      if (this.conversations[conversationId] === undefined || markAsRead) {
        await this.loadConversation(conversationId, markAsRead)
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
    async loadConversation (conversationId, markAsRead, config = {}) {
      const response = await api.getConversation(conversationId, markAsRead, config)
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
      if (conversationId in this.conversations) {
        this.assignMessageToStore(conversationId, data.message)
      } else {
        await this.loadConversation(conversationId, false)
      }
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
      // Idempotency key: a retry of a failed send reuses this key so the server
      // deduplicates it instead of storing a second message.
      const clientKey = generateClientKey()
      try {
        const message = await api.sendMessage(conversationId, messageText, clientKey)
        if (!message?.id) {
          throw new Error('sendMessage answered without a message')
        }
        this.assignMessageToStore(conversationId, message)
      } catch (e) {
        const errorMessage = {
          id: this.failureMessageId,
          body: messageText,
          clientKey,
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
    async openMultiChat (participantIds, preface = null) {
      const uniqueParticipantIds = Array.from(new Set(participantIds)) // remove duplicates
      const conversationId = await this.createConversation(uniqueParticipantIds)
      this.openChat(conversationId, preface)
    },
    async resendFailedMessage (conversationId, failureMessageId) {
      const message = this.conversations[conversationId].messages[failureMessageId]
      try {
        // Reuse the original key so a resend of an already-stored message is a no-op.
        const sentMessage = await api.sendMessage(conversationId, message.body, message.clientKey)
        if (!sentMessage?.id) {
          throw new Error('sendMessage answered without a message')
        }
        this.assignMessageToStore(conversationId, sentMessage)
        Vue.delete(this.conversations[conversationId].messages, failureMessageId)
      } catch (e) {
        // The failed entry stays, so the text is not lost and can be retried. Rethrowing
        // would only produce an unhandled rejection, the caller has no handler.
        pulseError(i18n('error_unexpected'))
        console.error('resendFailedMessage', e)
      }
    },
    openChat (conversationId, preface = null) {
      if (preface) {
        this.pendingPreface[String(conversationId)] = preface
        // Persist transient preface to sessionStorage so it survives full-page
        // navigations (mobile opens /msg?cid=...), then the chat UI can read
        // it.
        sessionStorage.setItem(`fs_pending_preface:${conversationId}`, JSON.stringify(preface))
      }
      if (this.messagePageOpenChatListener) {
        this.messagePageOpenChatListener(conversationId)
      } else if (this.messagePopupOpenChatListener) {
        this.messagePopupOpenChatListener(conversationId)
      } else {
        navigate(urls.conversations(conversationId))
      }
    },
    async openChatWithUser (userId, preface = null) {
      const conversation = await api.getConversationIdForConversationWithUser(userId)
      this.openChat(conversation.id, preface)
    },
    popPreface (conversationId) {
      const key = String(conversationId)
      const val = this.pendingPreface[key] || null
      if (val) { delete this.pendingPreface[key] }
      if (val) return val

      const sessionKey = `fs_pending_preface:${conversationId}`
      const sessionVal = sessionStorage.getItem(sessionKey)
      if (sessionVal) {
        sessionStorage.removeItem(sessionKey)
        try { return JSON.parse(sessionVal) } catch { return null }
      }

      return null
    },
    async renameConversation (conversationId, newName) {
      await api.renameConversation(conversationId, newName)
      if (this.conversations[conversationId]) {
        Vue.set(this.conversations[conversationId], 'title', newName)
      }
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
