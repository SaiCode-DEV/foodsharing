<template>
  <Dropdown
    :title="$t('navigation.notifications')"
    icon="fa-bell"
    :badge="unread"
    direction="right"
    is-fixed-size
    :is-scrollable="bells.length > 1"
  >
    <template v-if="bells.length > 0" #content>
      <NotificationsEntry
        v-for="bell in bells"
        :key="bell.id"
        :bell="bell"
        @remove="onBellDelete"
        @read="onBellRead"
      />
    </template>
    <template v-else #content>
      <small
        role="menuitem"
        class="disabled dropdown-item"
        v-text="$t('bell.no_bells')"
      />
    </template>
    <template #actions>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="reloadUncached()"
      >
        <i class="icon-subnav fas fa-sync" />
        {{ $t('menu.entry.refresh') }}
      </button>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        :class="{ 'disabled': allLoaded }"
        @click="loadMoreBells()"
      >
        <i class="icon-subnav fas fa-angle-double-down" />
        {{ $t('menu.entry.load_more') }}
      </button>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        :class="{ 'disabled': !unread }"
        @click="markNewBellsAsRead()"
      >
        <i class="icon-subnav fas fa-check-double" />
        {{ $t('menu.entry.mark_as_read') }}
      </button>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        :class="{ 'disabled': !readMessagesDisplayed }"
        @click="deleteAllReadBells()"
      >
        <i class="icon-subnav fas fa-trash" />
        {{ $t('menu.entry.delete_read') }}
      </button>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import DataBell from '@/stores/bells'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import NotificationsEntry from './NavNotificationsEntry'
// Mixins
import { pulseError } from '@/script'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

export default {
  components: {
    NotificationsEntry,
    Dropdown,
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  computed: {
    bells () {
      const bells = DataBell.getters.get() // returns vue mutation object, you can not sort on this, without driving vue crazy// !
      return bells.concat().sort((a, b) => a.isRead - b.isRead)
    },
    allLoaded () {
      return DataBell.getters.getAreAllLoaded()
    },
    readMessagesDisplayed () {
      return this.bells.some(b => b.isRead)
    },
    unread () {
      let { count, maybeMore: plus } = DataBell.getters.getUnreadCount()
      if (!count) return ''
      if (count > 99) {
        count = 99
        plus = true
      }
      return `${count}${plus ? '+' : ''}`
    },
  },
  methods: {
    async onBellDelete (id) {
      try {
        await DataBell.mutations.delete([id])
      } catch (err) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async onBellRead (bell) {
      if (!bell.isRead) {
        try {
          await DataBell.mutations.markAsRead(bell)
        } catch (err) {
          pulseError(this.$t('error_unexpected'))
        }
      }
    },
    async markNewBellsAsRead () {
      try {
        await DataBell.mutations.markNewBellsAsRead()
      } catch {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async loadMoreBells () {
      try {
        await DataBell.mutations.loadMore()
      } catch {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async reloadUncached () {
      try {
        await DataBell.mutations.fetch(true)
      } catch {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async deleteAllReadBells () {
      const ids = this.bells.filter(b => b.isRead).map(b => b.id)
      const params = { count: ids.length }
      if (!await this.confirmationDialogue('menu.bell.delete_read_confirmation.text', { params })) return
      try {
        await DataBell.mutations.delete(ids)
      } catch {
        pulseError(this.$t('error_unexpected'))
      }
    },
  },
}
</script>
