<template>
  <div class="chatboxhead" @click="$emit('toggle')">
    <div class="chatboxtitle">
      <!-- Titled chat: show the title, plus avatars and an overflow count -->
      <template v-if="box.title">
        <a
          v-if="box.storeId"
          class="box-title"
          :href="url('store', box.storeId)"
          @click.stop
        >
          <i class="mdi mdi-chat mdi-flip-h" />
          {{ box.title }}
        </a>
        <span
          v-else
          class="box-title"
        >
          <i class="mdi mdi-chat mdi-flip-h" />
          {{ box.title }}
        </span>
        <span
          v-if="box.participants && box.participants.length"
          class="participant-stack"
        >
          <span ref="avatarsRef" class="participant-avatars">
            <b-avatar
              v-for="p in box.participants.slice(0, visibleAvatars)"
              :key="p.id"
              v-b-tooltip.hover
              :title="p.name ?? $t('chat.unknown_username')"
              :src="p.avatar"
              :size="18"
              :variant="p.avatar ? 'light' : 'secondary'"
              :href="$url('profile', p.id)"
              @click.stop
            />
          </span>
        </span>
      </template>
      <!-- Unnamed chat: < 4 => name + avatars; >=4 => avatars only -->
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
              :variant="p.avatar ? 'light' : 'secondary'"
              class="mr-1"
            />
            <span>{{ p.name ?? $t('chat.unknown_username') }}</span>
          </a>
        </template>
        <template v-else>
          <span class="participant-stack">
            <span ref="avatarsRef" class="participant-avatars">
              <b-avatar
                v-for="p in box.participants.slice(0, visibleAvatars)"
                :key="p.id"
                v-b-tooltip.hover
                :title="p.name ?? $t('chat.unknown_username')"
                :src="p.avatar"
                :size="18"
                :variant="p.avatar ? 'light' : 'secondary'"
                :href="$url('profile', p.id)"
                @click.stop
              />
            </span>
          </span>
        </template>
      </template>
      <i v-else class="fas fa-fw fa-spinner fa-spin" />
    </div>
    <div class="chatboxoptions">
      <span
        v-if="overflowCount > 0"
        v-b-tooltip.hover
        :title="$t('chat.show_participants')"
        class="participant-overflow"
        @click.stop="$emit('show-participants')"
      >+{{ overflowCount }}</span>
      <ChatUnreadIndicator :unread="unread" />
      <OverflowMenu
        icon="ellipsis-v"
        variant="link"
        :title="$t('options')"
        :float-right="false"
        :options="menuOptions"
        :callback-args="[box]"
        :direction="box.minimized ? 'up' : 'down'"
      />
      <b-button
        v-b-tooltip.hover.noninteractive
        :title="$t('button.close')"
        href="#"
        variant="link"
        size="sm"
        @click.prevent="$emit('close')"
      >
        <i class="fas fa-fw fa-times" />
      </b-button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { url } from '@/helper/urls'
import OverflowMenu from '@/components/OverflowMenu.vue'
import ChatUnreadIndicator from '@/components/Chat/ChatUnreadIndicator.vue'

const props = defineProps({
  box: { type: Object, required: true },
  unread: { type: Number, required: true },
  menuOptions: { type: Array, required: true },
})

defineEmits(['toggle', 'close', 'show-participants'])

const avatarsRef = ref(null)
const visibleAvatars = ref(20)
let observer = null

const overflowCount = computed(() => {
  if (!props.box.participants) return 0
  const total = props.box.participants.length
  // For unnamed chats with < 4 people, no overflow is shown
  if (!props.box.title && total < 4) return 0
  return Math.max(0, total - visibleAvatars.value)
})

function updateVisibleCount () {
  const el = avatarsRef.value
  if (!el) return
  // The avatar row sits under the absolutely positioned options block (chip +
  // menu + close), so clientWidth overstates the free space and the last avatar
  // ends up half-hidden behind the chip. Measure up to the options' left edge.
  const options = el.closest('.chatboxhead')?.querySelector('.chatboxoptions')
  const avatarsLeft = el.getBoundingClientRect().left
  const rightEdge = options
    ? options.getBoundingClientRect().left
    : el.getBoundingClientRect().right
  const available = rightEdge - avatarsLeft
  const maxRendered = Math.min(20, props.box.participants?.length || 0)
  // 18px avatar + 4px gap = 22px per avatar
  visibleAvatars.value = Math.max(0, Math.min(maxRendered, Math.floor((available + 4) / 22)))
}

function observeTargets () {
  if (!observer || !avatarsRef.value) return
  observer.observe(avatarsRef.value)
  // also watch the options block: when the chip appears it grows and shifts the
  // avatars' right edge, so the count has to be recomputed.
  const options = avatarsRef.value.closest('.chatboxhead')?.querySelector('.chatboxoptions')
  if (options) observer.observe(options)
  updateVisibleCount()
}

onMounted(() => {
  observer = new ResizeObserver(() => {
    updateVisibleCount()
  })
  observeTargets()
})

// If the ref changes (e.g., from v-if toggle), re-observe
watch(avatarsRef, (newEl, oldEl) => {
  if (oldEl && observer) observer.unobserve(oldEl)
  if (newEl) observeTargets()
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<style scoped lang="scss">
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

  .participant-overflow {
    width: auto;
    flex: 0 0 auto;
    cursor: pointer;
  }

  ::v-deep > .btn {
    padding: 0.25em;
    padding-left: 0.1em;
  }

  ::v-deep .overflow-menu {
    padding: 0.2em 0;

    .btn {
      color: var(--fs-color-primary-900);
      padding: 0.25em 0.5em;
    }
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

  .box-title {
    width: auto;
    flex: 0 0 auto;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .participant-stack {
    width: auto;
    display: inline-flex;
    align-items: center;
    align-self: center;
    gap: 4px;
    margin-left: 8px;
    margin-right: 40px;
    flex: 1 1 auto;
    min-width: 0;

    .participant-avatars {
      display: inline-flex;
      gap: 4px;
      flex: 1 1 auto;
      min-width: 0;
      overflow: hidden;

      ::v-deep .b-avatar {
        flex: 0 0 auto;
      }
    }
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

.participants-item:not(:last-child) {
  margin-right: 5px;
  &::after {
    content: ',';
  }
}
</style>
