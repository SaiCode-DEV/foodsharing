<template>
  <ChatComponent :chat-id="chatId" />
</template>

<script>

// Stores
import conversationStore from '@/stores/conversations'
// Components
import ChatComponent from './ChatComponent'

import { GET } from '@/browser'

export default {
  components: { ChatComponent },
  data () {
    return {
      chatId: null,
    }
  },
  mounted () {
    conversationStore.messagePageOpenChatListener = this.openChat // turn on opening chats in this component
    if (GET('cid')) {
      this.chatId = Number(GET('cid'))
    }
  },
  destroyed () {
    conversationStore.messagePageOpenChatListener = null // turn off opening chats in this component
  },
  methods: {
    openChat (chatId) {
      this.chatId = chatId
    },
  },
}
</script>

<style lang="scss" scoped>

</style>
