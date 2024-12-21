<template>
  <ul class="metanav">
    <Logo v-if="!viewIsMobile" />
    <NavItem
      v-for="(entry, idx) of metaNav"
      :key="idx"
      :entry="entry"
    />
    <NavAdmin v-if="!useRestrictedNavigation" />
  </ul>
</template>

<script>
// Data
import MetaNavData from '../../Data/MetaNavData.json'
//
import Logo from '@/components/Navigation/Logo'
import NavItem from '@/components/Navigation/_NavItems/NavItem'
import NavAdmin from '@/components/Navigation/Admin/NavAdmin'
//
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  components: {
    Logo,
    NavItem,
    NavAdmin,
  },
  mixins: [MediaQueryMixin, RouteAndDeviceCheckMixin],
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    useRestrictedNavigation () {
      return userStore.isApiRestrictedForLegalReasons
    },
    metaNav () {
      let nav = MetaNavData
      if (this.useRestrictedNavigation) {
        nav = nav.filter(entry => !entry.isRestricted)
      }
      return nav
    },
  },
}
</script>
