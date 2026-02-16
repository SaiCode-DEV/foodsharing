<template>
  <div
    v-if="isLoggedIn && !hideChatdock"
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
        <div class="chatboxhead" @click="toggle(box)">
          <div class="chatboxtitle">
            <!-- If conversation has a title, show it -->
            <a
              v-if="box.title && box.storeId"
              :href="url('store', box.storeId)"
              @click.stop
            >
              <i class="mdi mdi-chat mdi-flip-h" />
              {{ box.title }}
            </a>
            <span v-else-if="box.title">
              <i class="mdi mdi-chat mdi-flip-h" />
              {{ box.title }}
            </span>
            <!-- Otherwise show participants: < 4 => name + avatars; >=4 => avatars and menu shows overlay -->
            <template v-else-if="box.participants && box.participants.length">
              <template v-if="box.participants.length < 4">
                <a
                  v-for="p in box.participants"
                  :key="p.id"
                  class="participants-item"
                  :href="$url('profile', p.id)"
                  @click.stop
                >
                  <b-avatar
                    :src="p.avatar"
                    :size="18"
                    class="mr-1"
                  />
                  <span>{{ p.name }}</span>
                </a>
              </template>
              <template v-else>
                <b-avatar
                  v-for="p in box.participants.slice(0, 20)"
                  :key="p.id"
                  v-b-tooltip.hover
                  :title="p.name"
                  :src="p.avatar"
                  :size="18"
                  class="mr-1"
                  :href="$url('profile', p.id)"
                  @click.stop
                />
              </template>
            </template>
            <i v-else class="fas fa-fw fa-spinner fa-spin" />
          </div>
          <div class="chatboxoptions">
            <div
              v-if="unreadCount(box.id) !== 0"
              class="chatbox-unread-count"
            >
              <b-badge
                variant="info"
                pill
              >
                {{ unreadCount(box.id) > 0 ? unreadCount(box.id) : '&nbsp;' }}
              </b-badge>
            </div>
            <OverflowMenu
              icon="ellipsis-v"
              variant="link"
              :float-right="false"
              :options="getMenuOptions(box)"
              :callback-args="[box]"
            />
            <b-button
              :title="$t('button.close')"
              href="#"
              variant="link"
              size="sm"
              @click.prevent="closeBox(box.id)"
            >
              <i class="fas fa-fw fa-times" />
            </b-button>
          </div>
        </div>
        <div class="chatboxcontent" :class="{ 'minimized': box.minimized }">
          <ChatComponent
            ref="chatComponent"
            :popup-mode="true"
            :popup-opened-explicitly="!box.restoringFromSave"
            :chat-id="box.id"
          />
        </div>
        <!-- Participants overlay for >4 members -->
        <b-modal
          v-model="box.showMembersDialog"
          :title="$t('chat.participants')"
          size="lg"
          ok-only
          :ok-title="$t('button.close')"
          scrollable
        >
          <div class="participants-grid">
            <b-button
              v-for="p in box.participants"
              :key="p.id"
              :href="$url('profile', p.id)"
              variant="secondary"
              class="participant-card d-flex flex-row justify-content-between align-items-center py-0"
              @click.stop
            >
              <div>
                <b-avatar
                  :src="p.avatar"
                  size="24"
                  class="mr-2"
                />
                {{ p.name }}
              </div>
              <b-button
                v-b-tooltip.hover="$t('chat.open_chat')"
                variant="outline-success"
                size="sm"
                class="ml-2 my-1"
                @click.prevent="openChatWithUser(p.id, box.id)"
              >
                <i class="fas fa-message" />
              </b-button>
            </b-button>
          </div>
        </b-modal>
      </div>
    </transition-group>
    <b-modal
      v-model="renameDialogVisible"
      :title="$t('chat.rename')"
      size="md"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      @ok="saveRename"
      @hidden="resetRenameDialog"
    >
      <b-form-group
        :label="$t('chat.rename')"
        label-for="rename-input"
      >
        <small class="text-muted">
          {{ $t('chat.rename_empty_hint') }}
        </small>
        <b-form-input
          id="rename-input"
          v-model="newTitle"
          :placeholder="$t('chat.rename_placeholder')"
          maxlength="50"
          autofocus
        />
      </b-form-group>
    </b-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import ChatComponent from '@/views/pages/Message/ChatComponent.vue'
import conversationStore from '@/stores/conversations'
import Storage from '@/storage'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { isMob, pulseError } from '@/script'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import OverflowMenu from '@/components/OverflowMenu.vue'

const userStore = useUserStore()
const storage = new Storage('conversations')
const boxes = ref([])
const isLoggedIn = computed(() => userStore.isLoggedIn)
const hideChatdock = computed(() => location.pathname === '/msg')
const chatComponent = ref(null)

// Rename dialog state
const renameDialogVisible = ref(false)
const currentRenameBoxId = ref(null)
const newTitle = ref('')

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
      callback: (box) => conversationStore.setReadStatus(box.id, false),
    },
    {
      textKey: 'chat.rename',
      icon: 'edit',
      hide: box.storeId,
      callback: (box) => rename(box.id),
    },
    {
      textKey: 'chat.show_participants',
      icon: 'users',
      hide: box.participants.length <= 2,
      callback: (box) => { box.showMembersDialog = true },
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

    await conversationStore.loadConversation(id, markAsRead)
    const conv = conversationStore.conversations[id]

    if (conv.title) {
      box.title = conv.title
    }

    if (conv.storeId) {
      box.storeId = conv.storeId
    } else if (Array.isArray(conv.members)) {
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
    console.error('Failed to load conversation for chat dock:', e)
    pulseError(i18n('chat.error.loading_conversation'))
  }
}

function openChatWithUser (userId, conversationId = null) {
  conversationStore.openChatWithUser(userId)
  const box = getBoxForConversation(conversationId)
  if (box) {
    box.showMembersDialog = false
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

function rename (id) {
  const box = boxes.value.find(b => b.id === id)
  if (!box) return
  if (box.storeId) return

  currentRenameBoxId.value = id
  newTitle.value = box.title || ''
  renameDialogVisible.value = true
}

async function saveRename () {
  try {
    await conversationStore.renameConversation(currentRenameBoxId.value, newTitle.value.trim())
    const box = boxes.value.find(b => b.id === currentRenameBoxId.value)
    if (box) {
      box.title = newTitle.value.trim() || null
    }
  } catch (e) {
    console.error('Failed to rename conversation:', e)
    pulseError(i18n('chat.error.renaming_conversation'))
  }
}

function resetRenameDialog () {
  currentRenameBoxId.value = null
  newTitle.value = ''
}

function loadSavedChats () {
  const saved = storage.get('msg-chats')
  if (saved && Array.isArray(saved)) {
    // Reverse the array since openChat uses unshift
    saved.reverse().forEach(s => openChat(s.id, { minimized: s.min, restoringFromSave: true }))
  }
}

onMounted(() => {
  if (hideChatdock.value) {
    return
  }

  // intercept store openChat for popups
  if (!isMob()) {
    conversationStore.messagePopupOpenChatListener = (id) => openChat(id)
  }

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

.chatboxhead {
  background-color: var(--fs-color-primary-300);
  border-radius: 6px 6px 0 0;
  box-shadow: 0 2px 6px 0 rgba(0, 0, 0, 0.15);
  z-index: 1;
  padding: 0;
  color: var(--fs-color-primary-900);
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  cursor: pointer;
  outline: 0;
  height: 32px;
  position: relative;
}

.chatboxoptions {
  position: absolute;
  right: 0;
  bottom: 0;
  flex-shrink: 0;
  align-self: self-end;
  padding-left: 20px;
  padding-right: 5px;
  z-index: 1;
  overflow: visible;
  background: linear-gradient(to right, #0000 0%, var(--fs-color-primary-300) 8%);
  border-radius: 0 6px 0 0;
  display: flex;
  align-items: center;
  gap: 2px;

  ::v-deep > .btn {
    padding: 0.25em;
    padding-left: 0.1em;
  }

  ::v-deep .overflow-menu .btn {
    color: var(--fs-color-primary-900);
    padding: 0.25em 0.5em;
  }
}

.chatbox-unread-count {
  padding: 0.25em 0.5em;

  .badge {
    padding: 0.25em;
    min-width: 1.5em;
    min-height: 1.5em;
    margin-bottom: 0.4em;
  }
}

.chatboxtitle {
  padding: 6.5px;
  display: flex;
  flex-direction: row;
  float: left;
  text-decoration: none;
  font-size: 13px;
  font-weight: bold;
  outline: 0;
  white-space: nowrap;
  position: absolute;
  width: 100%;
  overflow: hidden;
  span {
    width: 10px;
    vertical-align: middle;
  }
  a {
    color: currentColor !important;
  }
  a:hover {
    text-decoration: none;
    color: currentColor !important;
  }
  i {
    margin-right: 5px;
    color: currentColor !important;
  }
}

.chatboxcontent {
  font-size: 13px;
  color: var(--fs-color-dark);
  background-color: var(--fs-color-light);
  border-right: 1px solid var(--fs-border-default);
  border-left: 1px solid var(--fs-border-default);
}

.participants-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
  .participant-card {
    background-color: var(--fs-color-primary-200);
    border: 0;
    color: var(--fs-color-black);
  }
}

.participants-item:not(:last-child) {
    margin-right: 5px;
    &::after {
      content: ',';
    }
  }
</style>
