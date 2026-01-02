<template>
  <ul v-if="!viewIsMobile" class="metanav">
    <Logo />
    <NavItem
      v-for="(entry, idx) of metaNav"
      :key="idx"
      :entry="entry"
    />
    <NavAdmin />
  </ul>
  <div v-else class="w-100">
    <NavItem
      v-for="entry of mobileTopNav"
      :key="entry.title"
      :entry="entry"
    />
    <HelpDropdown />
    <NavAdmin />
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
    metaNav () {
      return MetaNavData
    },
    mobileTopNav () {
      return MetaNavData
        .filter(entry => ['navigation.map', 'navigation.promotion'].includes(entry.title))
        .filter(entry => !entry.isRestricted || !this.useRestrictedNavigation)
    },
  },
}
</script>
