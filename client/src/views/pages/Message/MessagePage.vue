<template>
  <ChatComponent
    :chat-id="chatId"
    :ask-for-push-notifications="true"
  />
</template>

<script setup>
import { computed, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import conversationStore from '@/stores/conversations'
import ChatComponent from './ChatComponent'
import { urls } from '@/helper/urls'
import { navigate } from '@/helper/router'

const { proxy } = getCurrentInstance()
const chatId = computed(() => proxy.$route.query.cid ? Number(proxy.$route.query.cid) : null)

const openChat = (newChatId) => {
  if (String(chatId.value) !== String(newChatId)) {
    navigate(urls.conversations(newChatId))
  }
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
