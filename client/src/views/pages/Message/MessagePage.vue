<template>
  <ChatComponent
    :chat-id="chatId"
    :ask-for-push-notifications="true"
  />
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import conversationStore from '@/stores/conversations'
import ChatComponent from './ChatComponent'
import { GET } from '@/browser'

// Initialize chatId from URL parameter before mounting
const chatId = ref(GET('cid') ? Number(GET('cid')) : null)

const openChat = (newChatId) => {
  chatId.value = newChatId
}

onMounted(() => {
  conversationStore.messagePageOpenChatListener = openChat // turn on opening chats in this component
})

onUnmounted(() => {
  conversationStore.messagePageOpenChatListener = null // turn off opening chats in this component
})
</script>
