<template>
  <ul class="mainnav">
    <Logo v-if="viewIsMobile" />
    <NavLink
      v-if="!isFoodsaver"
      :title="$t('foodsaver.upgrade.FOODSAVER')"
      icon="fa-hands-helping"
      :href="$url('quiz_foodsaver')"
    />
    <NavRegions v-if="isFoodsaver && !viewIsMobile" />
    <NavGroups v-if="isFoodsaver && !viewIsMobile" />
    <NavStores v-if="isFoodsaver" />
    <NavBaskets />
    <NavConversations v-if="viewIsMobile" />
    <NavNotifications v-if="viewIsMobile" />
    <b-navbar-toggle target="nav-collapse">
      <template #default="{ expanded }">
        <span
          v-if="hasMailBox && getMailUnreadCount"
          class="badge badge-danger"
          :class="{
            'onlyNine': String(getMailUnreadCount).length === 1,
            'overNinetyNine': String(getMailUnreadCount).length > 2,
          }"
          v-text="getMailUnreadCount"
        />
        <i
          class="fas"
          :class="{
            'fa-bars': !expanded,
            'fa-times': expanded,
          }"
        />
      </template>
    </b-navbar-toggle>
  </ul>
</template>

<script>
// Store
import { useUserStore } from '@/stores/user'
//
import NavLink from '@/components/Navigation/_NavItems/NavLink'
import Logo from '@/components/Navigation/Logo'
//
import NavNotifications from '@/components/Navigation/Notifications/NavNotifications'
import NavConversations from '@/components/Navigation/Conversations/NavConversations'
import NavBaskets from '@/components/Navigation/Baskets/NavBaskets'
import NavStores from '@/components/Navigation/Stores/NavStores'
import NavGroups from '@/components/Navigation/Groups/NavGroups'
import NavRegions from '@/components/Navigation/Regions/NavRegions'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: {
    Logo,
    NavLink,
    NavNotifications,
    NavConversations,
    NavBaskets,
    NavStores,
    NavGroups,
    NavRegions,
  },
  mixins: [MediaQueryMixin],
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    isFoodsaver () {
      return this.userStore.isFoodsaver
    },
    hasMailBox () {
      return this.userStore.hasMailBox
    },
    getMailUnreadCount () {
      return this.userStore.getMailUnreadCount
    },
  },
}
</script>
