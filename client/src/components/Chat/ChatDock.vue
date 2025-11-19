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
            <span v-if="box.title">
              <i class="mdi mdi-chat mdi-flip-h" />
              {{ box.title }}
            </span>
            <!-- Otherwise show participants: 1 => name; >2 => avatars; >4 => avatars and menu shows overlay -->
            <template v-else-if="box.participants && box.participants.length">
              <template v-if="box.participants.length === 1">
                <a
                  :href="$url('profile', box.participants[0].id)"
                  @click.stop
                >
                  <b-avatar
                    :src="box.participants[0].avatar"
                    :size="18"
                    class="mr-1"
                  />
                  <span>{{ box.participants[0].name }}</span>
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
            <b-dropdown
              no-caret
              size="sm"
              :dropup="box.minimized"
              right
              variant="link"
              :title="$t('terminology.settings')"
            >
              <template #button-content>
                <i class="fas fa-fw fa-cog" />
              </template>
              <b-dropdown-item :href="$url('conversations', box.id)">
                {{ $t('menu.entry.all_messages') }}
              </b-dropdown-item>
              <b-dropdown-item
                v-if="box.participants.length > 4"
                @click.stop.prevent="box.showMembersDialog = true"
              >
                {{ $t('chat.show_participants') }}
              </b-dropdown-item>
              <b-dropdown-item href="#" @click.stop="closeAll">
                {{ $t('menu.entry.close_all_chats') }}
              </b-dropdown-item>
            </b-dropdown>
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
          scrollable
        >
          <ul class="list-unstyled m-0">
            <li
              v-for="p in box.participants"
              :key="p.id"
              class="d-flex align-items-center mb-1"
            >
              <b-avatar
                :src="p.avatar"
                size="24"
                class="mr-2"
              />
              <a :href="$url('profile', p.id)">{{ p.name }}</a>
            </li>
          </ul>
        </b-modal>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import ChatComponent from '@/views/pages/Message/ChatComponent.vue'
import conversationStore from '@/stores/conversations'
import Storage from '@/storage'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const userStore = useUserStore()
const storage = new Storage('conversations')
const boxes = ref([])
const isLoggedIn = computed(() => userStore.isLoggedIn)

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
    await conversationStore.loadConversation(id)
    const conv = conversationStore.conversations[id]
    const title = conv.title || ''
    const box = boxes.value.find(b => b.id === id)
    if (box) {
      if (title) {
        box.title = title
        box.participants = []
        box.showMembersDialog = false
      } else if (Array.isArray(conv.members)) {
        const currentId = userStore.getUserId
        const participantIds = conv.members.filter(m => m !== currentId)
        const participants = participantIds
          .map(id => {
            const profile = ProfileStore.profiles?.[id]
            return profile ? { id, name: profile.name, avatar: profile.avatar } : null
          })
          .filter(Boolean)
        box.title = ''
        box.participants = participants
        box.showMembersDialog = false
      } else {
        box.title = ''
        box.participants = []
        box.showMembersDialog = false
      }
    }
  } catch (e) {
    console.error('Failed to load conversation for chat dock:', e)
    pulseError(i18n('chat.error.loading_conversation'))
  }
}

function openChat (id, min = false) {
  if (!boxes.value.some(b => b.id === id)) {
    boxes.value.push({
      id,
      minimized: !!min,
      title: '',
      participants: [],
      showMembersDialog: false,
      settingsOpen: false,
    })
    ensureTitle(id)
    persist()
  }
}

onMounted(() => {
  // intercept store openChat for popups
  conversationStore.messagePopupOpenChatListener = (id) => openChat(id)
  const saved = storage.get('msg-chats')
  if (saved && Array.isArray(saved)) {
    saved.forEach(s => openChat(s.id, s.min))
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
  z-index: 1020; /* above navbar */
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
  pointer-events: auto; /* re-enable interaction inside boxes */
  bottom: 0;
  transition: bottom 0.3s ease;
}

.chat-dock__box:has(.minimized) {
  bottom: -400px;
}

.chatbox-enter-active, .chatbox-leave-active { transition: opacity .15s; }
.chatbox-enter-from, .chatbox-leave-to { opacity: 0; }

.chatboxhead {
  background-color: var(--fs-color-primary-300);
  padding: 0;
  color: var(--fs-color-primary-900);
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  cursor: pointer;
  outline: 0;
}

.chatboxoptions {
  flex-shrink: 0;
  align-self: self-end;
  margin-left: 20px;
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
  text-overflow: ellipsis;
  white-space: nowrap;
  overflow: hidden;
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
</style>
