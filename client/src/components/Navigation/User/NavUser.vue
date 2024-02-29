<template>
  <Dropdown
    class="nav-user is-open-on-mobile"
    :title="$i18n('navigation.profil', {name: getUserFirstName})"
    direction="right"
    :badge="getMailUnreadCount"
  >
    <template #icon>
      <Avatar
        :size="24"
        :user="{ avatar: getAvatar }"
        class="icon-subnav"
      />
    </template>
    <template #content>
      <a
        v-if="hasMailBox"
        :title="$i18n('menu.entry.mailbox')"
        :href="$url('mailbox')"
        role="menuitem"
        class="dropdown-item dropdown-action position-relative"
      >
        <i class="icon-subnav fas fa-envelope" />
        {{ $i18n('menu.entry.mailbox') }}
        <div class="badge badge-danger badge-inline">{{ getMailUnreadCount }}</div>
      </a>
      <div v-if="hasMailBox" class="dropdown-divider" />
      <a
        :href="$url('profile', getUserId)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-address-card" /> {{ $i18n('profile.title') }}
      </a>
      <a
        :href="$url('settings')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-cog" /> {{ $i18n('settings.header') }}
      </a>
      <div class="dropdown-divider" />
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click.prevent="$bvModal.show('languageChooserModal')"
      >
        <i class="icon-subnav fas fa-language" /> {{ $i18n('menu.entry.language') }}
      </button>
    </template>
    <template #actions>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="deleteCaches()"
      >
        <i class="icon-subnav fas fa-power-off" /> {{ $i18n('login.logout') }}
      </button>
    </template>
  </Dropdown>
</template>
<script>
// Stores
import DataUser from '@/stores/user'
// Components
import Avatar from '@/components/Avatar/Avatar.vue'
import Dropdown from '../_NavItems/NavDropdown'
// Mixins
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
import { clearCaches } from '@/helper/cache'

export default {
  components: {
    Avatar,
    Dropdown,
  },
  mixins: [RouteCheckMixin],
  computed: {
    getAvatar () {
      return DataUser.getters.getAvatar()
    },
    getUserFirstName () {
      return DataUser.getters.getUserFirstName()
    },
    getUserId () {
      return DataUser.getters.getUserId()
    },
    getMailUnreadCount () {
      return DataUser.getters.getMailUnreadCount()
    },
    hasMailBox () {
      return DataUser.getters.hasMailBox()
    },
  },
  methods: {
    async deleteCaches () {
      await clearCaches()
      window.location.href = this.$url('logout')
    },
  },
}
</script>
