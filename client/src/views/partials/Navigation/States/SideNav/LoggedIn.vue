<template>
  <div class="navbar-navside">
    <ul class="sidenav">
      <NavRegions v-if="isFoodsaver && viewIsMobile" />
      <NavGroups v-if="isFoodsaver && viewIsMobile" />
      <Link
        v-if="isFoodsaver"
        icon="fa-search"
        title="Suche"
        modal="searchBarModal"
      />
      <NavConversations v-if="!viewIsMobile" />
      <NavNotifications v-if="!viewIsMobile" />
      <NavUser />
    </ul>
    <MetaNavLoggedIn v-if="viewIsMobile" />
  </div>
</template>

<script>
// Store
import DataUser from '@/stores/user'
//
import Link from '@/components/Navigation/_NavItems/NavLink'
import NavConversations from '@/components/Navigation/Conversations/NavConversations'
import NavNotifications from '@/components/Navigation/Notifications/NavNotifications'
import NavUser from '@/components/Navigation/User/NavUser'
import NavGroups from '@/components/Navigation/Groups/NavGroups'
import NavRegions from '@/components/Navigation/Regions/NavRegions'
// State
import MetaNavLoggedIn from '../MetaNav/LoggedIn.vue'
//
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: {
    Link,
    NavConversations,
    NavNotifications,
    NavUser,
    NavGroups,
    NavRegions,
    MetaNavLoggedIn,
  },
  mixins: [MediaQueryMixin],
  computed: {
    isFoodsaver () {
      return DataUser.getters.isFoodsaver()
    },
  },
  mounted () {
    window.addEventListener('keypress', this.openSearchViaKeyCombinationHandler)
  },
  methods: {
    openSearchViaKeyCombinationHandler (event) {
      if (event.target === document.body && event.code === 'KeyF' && event.shiftKey) {
        this.$bvModal.show('searchBarModal')
      }
    },
  },
}
</script>
