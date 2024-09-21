<template>
  <b-navbar
    ref="navbar"
    toggleable="md"
    class="navigation"
    sticky
    :class="{
      'nav-not-visible': isLoggedIn,
      'nav-foodsharer': !isFoodsaver,
    }"
  >
    <DonationModal />
    <div class="metanav-container container">
      <MetaNavLoggedIn v-if="!viewIsMobile && isLoggedIn" />
      <MetaNavLoggedOut v-else-if="!viewIsMobile" />
    </div>
    <div class="container nav-container">
      <MainNavLoggedIn v-if="isLoggedIn" />
      <MainNavLoggedOut v-else />

      <b-collapse
        id="nav-collapse"
        is-nav
      >
        <SideNavLoggedIn v-if="isLoggedIn" />
        <SideNavLoggedOut v-else />
      </b-collapse>
    </div>
    <ModalLoader v-if="isLoggedIn" />
    <ThemeSwitcherModal />
  </b-navbar>
</template>

<script>
// Store
import { useUserStore } from '@/stores/user.js'
import DataBells from '@/stores/bells.js'
import DataStores from '@/stores/stores.js'
import DataBaskets from '@/stores/baskets.js'
import DataConversations from '@/stores/conversations.js'
import DataGroups from '@/stores/groups.js'
import { useRegionStore } from '@/stores/regions.js'
// States
import MetaNavLoggedIn from './States/MetaNav/LoggedIn.vue'
import MetaNavLoggedOut from './States/MetaNav/LoggedOut.vue'
import MainNavLoggedIn from './States/MainNav/LoggedIn.vue'
import MainNavLoggedOut from './States/MainNav/LoggedOut.vue'
import SideNavLoggedIn from './States/SideNav/LoggedIn.vue'
import SideNavLoggedOut from './States/SideNav/LoggedOut.vue'
// ModalLoader
import ModalLoader from '@/views/partials/Modals/ModalLoader.vue'
import DonationModal from '@/components/Modals/Donation/DonationModal.vue'
import ThemeSwitcherModal from '@/views/partials/Modals/ThemeSwitcherModal.vue'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

const userStore = useUserStore()
const regionStore = useRegionStore()

export default {
  name: 'Navigation',
  components: {
    ModalLoader,
    DonationModal,
    ThemeSwitcherModal,
    MetaNavLoggedIn,
    MetaNavLoggedOut,
    MainNavLoggedIn,
    MainNavLoggedOut,
    SideNavLoggedIn,
    SideNavLoggedOut,
  },
  mixins: [MediaQueryMixin],
  props: {
    regions: {
      type: Array,
      default: () => [],
    },
    groups: {
      type: Array,
      default: () => [],
    },
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      navIsSmall: false,
    }
  },
  computed: {
    isLoggedIn () {
      return userStore.isLoggedIn
    },
    isFoodsaver () {
      return userStore.isFoodsaver
    },
    homeHref () {
      return (this.isLoggedIn) ? this.$url('dashboard') : this.$url('home')
    },
    userId () {
      return userStore.getUserId
    },
  },
  watch: {
    isFoodsaver: {
      async handler (newValue) {
        if (newValue) {
          await DataStores.mutations.fetch(false, this.userId)
        }
      },
      immediate: true,
      deep: true,
    },
  },
  async created () {
    // Load data
    if (this.isLoggedIn) {
      // TODO: NO APIS :(
      DataGroups.mutations.set(this.groups)
      regionStore.regions = this.regions
      await DataBaskets.mutations.fetchOwn()
      await DataBells.mutations.fetch()
      await DataConversations.initConversations()
    }
  },
  async mounted () {
    window.addEventListener('resize', this.resizeHandler)
    window.addEventListener('load', this.resizeHandler)
    if (userStore.hasMailBox) {
      userStore.fetchMailUnreadCount()
    }
  },
  methods: {
    resizeHandler () {
      const height = this.$refs.navbar.$el.getBoundingClientRect().height + 'px'
      document.documentElement.style.setProperty('--navbar-height', height)
    },
  },
}
</script>
