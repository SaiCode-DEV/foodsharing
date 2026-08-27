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

// Without `interactive-widget`, the on-screen keyboard pushes the chat header out
// of view (#2775). It has to be set from here: the head of the document is written
// once, while this page can be reached without loading a new one.
const CHAT_VIEWPORT = 'width=device-width, height=device-height, initial-scale=1.0, minimum-scale=1, maximum-scale=1, user-scalable=no, interactive-widget=resizes-content'
let previousViewport = null

function viewportTag () {
  return document.querySelector('meta[name="viewport"]')
}

onMounted(() => {
  conversationStore.messagePageOpenChatListener = openChat // turn on opening chats in this component

  const tag = viewportTag()
  if (tag) {
    previousViewport = tag.getAttribute('content')
    tag.setAttribute('content', CHAT_VIEWPORT)
  }
})

onUnmounted(() => {
  conversationStore.messagePageOpenChatListener = null // turn off opening chats in this component

  const tag = viewportTag()
  if (tag && previousViewport !== null) {
    tag.setAttribute('content', previousViewport)
  }
})
</script>
