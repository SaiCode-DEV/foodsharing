<template>
  <div
    class="chat-dock"
    aria-live="polite"
  >
    <transition-group
      name="chatbox"
      tag="div"
      class="chat-dock__boxes"
    >
      <div
        v-for="box in boxes"
        :key="box.id"
        class="chat-dock__box"
      >
        <ChatDockBoxHead
          :box="box"
          :unread="unreadCount(box.id)"
          :menu-options="getMenuOptions(box)"
          @toggle="toggle(box)"
          @close="closeBox(box.id)"
          @show-participants="getComponentForConversation(box.id)?.showParticipantsDialog()"
        />
        <div class="chatboxcontent" :class="{ 'minimized': box.minimized }">
          <ChatComponent
            ref="chatComponent"
            :popup-mode="true"
            :popup-opened-explicitly="!box.restoringFromSave"
            :chat-id="box.id"
            @save-rename="applyNewName"
          />
        </div>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import ChatComponent from '@/views/pages/Message/ChatComponent.vue'
import conversationStore from '@/stores/conversations'
import Storage from '@/storage'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { pulseError } from '@/script'
import { HTTP_RESPONSE } from '@/consts'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import ChatDockBoxHead from '@/components/Chat/ChatDockBoxHead.vue'

const userStore = useUserStore()
const storage = new Storage('conversations')
const boxes = ref([])
const chatComponent = ref(null)

function getMenuOptions (box) {
  return [
    {
      textKey: 'chat.open_full_view',
      icon: 'comments',
      href: url('conversations', box.id),
    },
    {
      textKey: 'chat.mark_as.unread',
      icon: 'eye-slash',
      hide: unreadCount(box.id) !== 0,
      callback: (box) => conversationStore.setReadStatus(box.id, false),
    },
    {
      textKey: 'chat.rename',
      icon: 'edit',
      hide: box.storeId,
      callback: (box) => initiateRename(box.id),
    },
    {
      textKey: 'chat.show_participants',
      icon: 'users',
      hide: box.participants.length <= 3,
      callback: (box) => { getComponentForConversation(box.id)?.showParticipantsDialog() },
    },
    {
      textKey: 'menu.entry.close_all_chats',
      icon: 'times-circle',
      callback: closeAll,
    },
  ]
}

function unreadCount (conversationId) {
  return conversationStore.conversations[conversationId]?.unreadMessages ?? 0
}

function persist () {
  const info = boxes.value.map(b => ({ id: Number(b.id), min: !!b.minimized }))
  if (info.length) storage.set('msg-chats', info)
  else storage.del('msg-chats')
}

function toggle (box) {
  if (box.minimized) {
    // If un-minimizing, tell the chat component, which may mark messages as read
    const chatComponentInstance = getComponentForConversation(box.id)
    if (chatComponentInstance) {
      setTimeout(() => {
        chatComponentInstance.handleUnMinimized()
      })
    }
  }
  box.minimized = !box.minimized
  persist()
}

function closeBox (id) {
  boxes.value = boxes.value.filter(b => b.id !== id)
  persist()
}

function closeAll () {
  boxes.value = []
  persist()
}

function getBoxForConversation (conversationId) {
  return boxes.value.find(b => b.id === conversationId)
}

function getComponentForConversation (conversationId) {
  return chatComponent.value?.find(comp => comp.roomId === conversationId) || null
}

async function ensureTitle (id, markAsRead) {
  try {
    const box = getBoxForConversation(id)
    if (!box) {
      return
    }

    await conversationStore.loadConversation(id, markAsRead, {
      skipErrorNotificationFor: [HTTP_RESPONSE.FORBIDDEN, HTTP_RESPONSE.NOT_FOUND],
    })
    const conv = conversationStore.conversations[id]

    if (conv.title) {
      box.title = conv.title
    }

    if (conv.storeId) {
      box.storeId = conv.storeId
    }

    if (Array.isArray(conv.members)) {
      const currentId = userStore.getUserId
      const participantIds = conv.members.filter(m => m !== currentId)
      const participants = participantIds
        .map(id => {
          const profile = ProfileStore.profiles?.[id]
          return profile ? { id, name: profile.name, avatar: profile.avatar } : null
        })
        .filter(Boolean)
      box.participants = participants
    }
  } catch (e) {
    // A window restored from an earlier session whose conversation is not
    // reachable anymore, e.g. after leaving a store team. Nothing the user did.
    // the api layer rejects with the status in `code`, axios keeps it in `response`
    const status = e.response?.status ?? e.code
    if (status === HTTP_RESPONSE.FORBIDDEN || status === HTTP_RESPONSE.NOT_FOUND) {
      closeBox(id)

      return
    }

    console.error('Failed to load conversation for chat dock:', e)
    pulseError(i18n('chat.error.loading_conversation'))
  }
}

function openChat (id, options = {}) {
  const openedByUser = !options?.minimized && !options?.restoringFromSave

  const existingBox = getBoxForConversation(id)
  if (existingBox) {
    if (!!options?.minimized !== existingBox.minimized) {
      existingBox.minimized = !!options?.minimized
    }
    if (openedByUser) {
      // Focus input if existing box is being opened
      const chatComponentInstance = getComponentForConversation(id)
      if (chatComponentInstance) {
        setTimeout(async () => {
          chatComponentInstance?.focusInput()
          await chatComponentInstance?.markMessagesAsRead()
        })
      }
    }
    return
  }

  boxes.value.unshift({
    id,
    minimized: !!options?.minimized,
    title: '',
    storeId: null,
    participants: [],
    showMembersDialog: false,
    restoringFromSave: !!options?.restoringFromSave,
  })
  ensureTitle(id, openedByUser)
  persist()
}

function initiateRename (id) {
  const box = getBoxForConversation(id)
  if (!box) return
  if (box.storeId) return

  getComponentForConversation(id)?.showRenameDialog()
}

// Ideally this should be handled by reactivity
async function applyNewName ({ conversationId, newTitle }) {
  const box = getBoxForConversation(conversationId)
  if (box) {
    box.title = newTitle
  }
}

function loadSavedChats () {
  const saved = storage.get('msg-chats')
  if (saved && Array.isArray(saved)) {
    // Reverse the array since openChat uses unshift
    saved.reverse().forEach(s => openChat(s.id, { minimized: s.min, restoringFromSave: true }))
  }
}

onMounted(() => {
  // intercept store openChat for popups
  conversationStore.messagePopupOpenChatListener = (id) => openChat(id)

  loadSavedChats()
})

onUnmounted(() => {
  conversationStore.messagePopupOpenChatListener = null
})

watch(boxes, persist, { deep: true })
</script>

<style scoped lang="scss">
.chat-dock {
  position: fixed;
  bottom: 0;
  right: 0;
  left: 0; /* span full width so flex can right-align items */
  pointer-events: none; /* allow clicks through when not on boxes */
}

.chat-dock__boxes {
  display: flex;
  flex-direction: row;
  justify-content: flex-end; /* stack boxes from the right */
  align-items: flex-end;
  gap: 10px;
  padding: 0px 10px; /* same spacing as before */
}

.chat-dock__box {
  position: relative;
  width: min(370px, calc(100vw - 20px)); /* max width 370px, min margin 10px each side */
  max-width: 370px;
  flex: 1 1 0;
  pointer-events: auto; /* re-enable interaction inside boxes */
  bottom: 0;
  transition: bottom 0.3s ease;
  border-radius: 6px 6px 0 0;
  box-shadow: 0 0 6px 0 rgba(0, 0, 0, 0.15);
}

.chat-dock__box:has(.minimized) {
  bottom: -400px;
}

.chatbox-move,
.chatbox-leave-active,
.chatbox-enter-active {
  transition: all 0.3s ease;
}
.chatbox-enter-from,
.chatbox-leave-to {
  opacity: 0;
  transform: translateX(400px);
}
/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.chatbox-leave-active {
  position: absolute;
}

.chatboxcontent {
  font-size: 13px;
  color: var(--fs-color-dark);
  background-color: var(--fs-color-light);
  border-right: 1px solid var(--fs-color-primary-300);
  border-left: 1px solid var(--fs-color-primary-300);
}

.participants-item:not(:last-child) {
  margin-right: 5px;
  &::after {
    content: ',';
  }
}
</style>
