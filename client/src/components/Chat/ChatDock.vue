<template>
  <div
    v-if="isLoggedIn"
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
        <div class="chatboxhead ui-corner-top" @click="toggle(box)">
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
            <OverflowMenu
              icon="cog"
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
          <ChatComponent :popup-mode="true" :chat-id="box.id" />
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

// Rename dialog state
const renameDialogVisible = ref(false)
const currentRenameBoxId = ref(null)
const newTitle = ref('')

function getMenuOptions (box) {
  return [
    {
      textKey: 'menu.entry.all_messages',
      href: url('conversations', box.id),
    },
    {
      textKey: 'chat.rename',
      hide: box.storeId,
      callback: (box) => rename(box.id),
    },
    {
      textKey: 'chat.show_participants',
      hide: box.participants.length <= 2,
      callback: (box) => { box.showMembersDialog = true },
    },
    {
      textKey: 'menu.entry.close_all_chats',
      callback: closeAll,
    },
  ]
}

function persist () {
  const info = boxes.value.map(b => ({ id: Number(b.id), min: !!b.minimized }))
  if (info.length) storage.set('msg-chats', info)
  else storage.del('msg-chats')
}

function toggle (box) {
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

async function ensureTitle (id) {
  try {
    const box = boxes.value.find(b => b.id === id)
    if (!box) {
      return
    }

    await conversationStore.loadConversation(id)
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
  const box = boxes.value.find(b => b.id === conversationId)
  if (box) {
    box.showMembersDialog = false
  }
}

function openChat (id, min = false) {
  if (boxes.value.some(b => b.id === id)) {
    return
  }

  boxes.value.unshift({
    id,
    minimized: !!min,
    title: '',
    storeId: null,
    participants: [],
    showMembersDialog: false,
  })
  ensureTitle(id)
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

onMounted(() => {
  // intercept store openChat for popups
  if (!isMob()) {
    conversationStore.messagePopupOpenChatListener = (id) => openChat(id)
  }
  const saved = storage.get('msg-chats')
  if (saved && Array.isArray(saved)) {
    // Reverse the array since openChat uses unshift
    saved.reverse().forEach(s => openChat(s.id, s.min))
  }
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
  padding: 0;
  color: var(--fs-color-primary-900);
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  cursor: pointer;
  outline: 0;
  height: 32px;
  position: relative
}

.chatboxoptions {
  position: absolute;
  right: 0;
  flex-shrink: 0;
  align-self: self-end;
  padding-left: 20px;
  padding-right: 5px;
  z-index: 1;
  overflow: visible;
  background: linear-gradient(to right, #0000 0%, var(--fs-color-primary-300) 8%);
  display: flex;
  align-items: center;
  gap: 2px;

  ::v-deep .overflow-menu .btn {
    color: var(--fs-color-primary-900);
  }
}

.chatboxtitle {
  padding: 7px;
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
