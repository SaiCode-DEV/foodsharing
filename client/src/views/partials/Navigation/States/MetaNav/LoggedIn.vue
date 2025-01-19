<template>
  <ul v-if="!viewIsMobile" class="metanav">
    <Logo />
    <NavItem
      v-for="(entry, idx) of metaNav"
      :key="idx"
      :entry="entry"
    />
    <NavAdmin v-if="!useRestrictedNavigation" />
  </ul>
  <div v-else class="w-100">
    <NavItem
      v-for="entry of mobileTopNav"
      :key="entry.title"
      :entry="entry"
    />
    <HelpDropdown />
    <NavAdmin v-if="!useRestrictedNavigation" />
  </div>
</template>

<script>
// Data
import MetaNavData from '../../Data/MetaNavData.json'
//
import Logo from '@/components/Navigation/Logo'
import NavItem from '@/components/Navigation/_NavItems/NavItem'
import NavAdmin from '@/components/Navigation/Admin/NavAdmin'
import HelpDropdown from '@/components/Navigation/Help/HelpDropdown'

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
    HelpDropdown,
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
    mobileTopNav () {
      return MetaNavData
        .filter(entry => ['navigation.map', 'navigation.promotion'].includes(entry.title))
        .filter(entry => !entry.isRestricted || !this.useRestrictedNavigation)
    },
  },
}
</script>
