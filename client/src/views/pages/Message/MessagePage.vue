<template>
  <main id="main">
    <div class="container">
      <div class="row">
        <div class="col px-0">
          <ChatComponent
            :chat-id="chatId"
            :ask-for-push-notifications="true"
          />
        </div>
      </div>
    </div>
  </main>
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

<style lang="scss" scoped>

</style>
