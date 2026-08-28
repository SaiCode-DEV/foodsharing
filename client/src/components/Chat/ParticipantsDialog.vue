<template>
  <b-modal
    v-model="visible"
    :title="$t('chat.participants')"
    size="lg"
    ok-only
    :ok-title="$t('button.close')"
    scrollable
  >
    <div class="participants-grid">
      <router-link
        v-for="p in participants"
        :key="p.id"
        :to="$url('profile', p.id)"
        class="participant-card btn d-flex flex-row justify-content-between align-items-center py-0"
        @click.native.stop="close"
      >
        <div
          v-b-tooltip.noninteractive="$t('profile.go')"
          class="d-flex align-items-center flex-grow-1"
        >
          <Avatar
            :user="p"
            :size="24"
            class="mr-2"
            shape="round"
          />
          {{ p.name }}
        </div>
        <b-button
          v-b-tooltip.noninteractive="$t('chat.open_chat')"
          variant="outline-primary"
          size="sm"
          class="ml-2 my-1"
          @click.prevent="openChatWithUser(p.id)"
        >
          <i class="fas fa-message" />
        </b-button>
      </router-link>
    </div>
  </b-modal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useUserStore } from '@/stores/user'
import conversationStore from '@/stores/conversations'
import ProfileStore from '@/stores/profiles'
import Avatar from '@/components/Avatar/Avatar.vue'

const visible = ref(false)
const conversation = ref(null)
const userStore = useUserStore()

const participants = computed(() => {
  if (!conversation.value) return []
  return conversation.value.members
    .filter(userId => userId !== userStore.getUserId)
    .map(id => {
      const profile = ProfileStore.profiles?.[id]
      return profile ? { id, name: profile.name, avatar: profile.avatar } : null
    })
    .filter(Boolean)
})

function open (conversationId) {
  conversation.value = conversationStore.conversations[conversationId]
  if (!conversation.value) {
    console.error('Conversation not found:', conversationId)
    return
  }
  visible.value = true
}

function close () {
  // the profile link navigates client side, so the dialog has to be closed explicitly
  visible.value = false
}

function openChatWithUser (userId) {
  visible.value = false
  conversationStore.openChatWithUser(userId)
}

defineExpose({
  open,
})
</script>

<style lang="css" scoped>
.participants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
}

.participant-card {
  background-color: var(--fs-color-primary-200);
  border: 0;
  color: var(--fs-color-black);
  text-decoration: none !important;

  &:hover {
    background-color: var(--fs-color-primary-300);
  }
}
</style>
