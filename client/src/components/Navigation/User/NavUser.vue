<template>
  <Dropdown
    class="nav-user is-open-on-mobile"
    :title="$t('navigation.profil', {name: getUserFirstName})"
    direction="right"
    :badge="hasMailBox ? userStore.getMailUnreadCount : null"
  >
    <template #icon>
      <Avatar
        :size="24"
        :user="{ avatar: getAvatar, isSleeping: userStore.isSleeping }"
        class="icon-subnav"
      />
    </template>
    <template #content>
      <FsLink
        v-if="hasMailBox"
        :to="$url('mailbox')"
        :title="$t('menu.entry.mailbox')"
        role="menuitem"
        class="dropdown-item dropdown-action position-relative"
      >
        <i class="icon-subnav fas fa-envelope" />
        {{ $t('menu.entry.mailbox') }}
        <div
          class="badge badge-danger badge-inline"
          :class="{ 'overNinetyNine': String(userStore.getMailUnreadCount).length > 2 }"
        >
          {{ userStore.getMailUnreadCount }}
        </div>
      </FsLink>
      <div v-if="hasMailBox" class="dropdown-divider" />
      <FsLink
        :to="$url('profile', getUserId)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-address-card" /> {{ $t('profile.title') }}
      </FsLink>
      <FsLink
        :to="$url('settings')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-cog" /> {{ $t('settings.header') }}
      </FsLink>
      <div class="dropdown-divider" />
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click.prevent="$bvModal.show('languageChooserModal')"
      >
        <i class="icon-subnav fas fa-language" /> {{ $t('menu.entry.language') }}
      </button>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click.prevent="$bvModal.show('themeSwitcherModal')"
      >
        <i
          class="icon-subnav fas fa-language"
          :class="themeStore.getCurrentIcon"
        /> {{ $t('theme_switcher.title') }}
      </button>
    </template>
    <template #actions>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="deleteCaches()"
      >
        <i class="icon-subnav fas fa-power-off" /> {{ $t('login.logout') }}
      </button>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import { useUserStore } from '@/stores/user'
import { useThemeStore } from '@/stores/theme'
// Components
import Avatar from '@/components/Avatar/Avatar.vue'
import Dropdown from '../_NavItems/NavDropdown'
import FsLink from '@/components/UI/FsLink.vue'

// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
import { clearCaches } from '@/helper/cache'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'
import serverData from '@/helper/server-data'
import Storage from '@/storage'

export default {
  components: {
    Avatar,
    Dropdown,
    FsLink,
  },
  mixins: [RouteCheckMixin],
  setup () {
    const userStore = useUserStore()
    const themeStore = useThemeStore()
    return {
      userStore,
      themeStore,
      serverData,
    }
  },
  computed: {
    getAvatar () {
      return this.userStore.getAvatar
    },
    getUserFirstName () {
      return this.userStore.getUserFirstName
    },
    getUserId () {
      return this.userStore.getUserId
    },
    hasMailBox () {
      return this.userStore.hasMailBox
    },
  },
  methods: {
    async deleteCaches () {
      try {
        const storage = new Storage('conversations')
        storage.del('msg-chats')
      } catch {}
      this.userStore.clearForLogout()
      await clearCaches()
      channel.postMessage({ type: BROADCAST_TYPE.LOGOUT })
      window.location.href = this.$url('logout')
    },
  },
}
</script>
