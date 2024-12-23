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
    <Loader />
    <DonationModal />
    <PetitionBanner v-if="!useRestrictedNavigation" />
    <div class="metanav-container container">
      <MetaNavLoggedIn v-if="!viewIsMobile && isLoggedIn" />
      <MetaNavLoggedOut v-else-if="!viewIsMobile" />
    </div>
    <div v-if="!useRestrictedNavigation" class="container nav-container">
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
    <div v-else class="container nav-container">
      <ul class="metanav">
        <ThemeSwitcher />
        <b-nav-item
          icon="fas fa-power-off"
          @click="deleteCaches()"
        >
          <slot name="icon">
            <i class="icon-nav fas fa-power-off" />
          </slot>
          <slot name="text">
            <span class="nav-text" v-text="$i18n('login.logout')" />
            <span class="sr-only" v-text="$i18n('login.logout')" />
          </slot>
        </b-nav-item>
      </ul>
    </div>
    <ModalLoader v-if="isLoggedIn && !useRestrictedNavigation" />
    <ThemeSwitcherModal />
  </b-navbar>
</template>

<script>
// Store
import { useUserStore } from '@/stores/user.js'
import DataBells from '@/stores/bells.js'
import DataStores from '@/stores/stores.js'
import { useBasketStore } from '@/stores/baskets'
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
import Loader from './Loader.vue'
import PetitionBanner from '@/views/partials/TopBanner/Petition/PetitionBanner.vue'
import { clearCaches } from '@/helper/cache'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'
import ThemeSwitcher from '@/components/ThemeSwitcher.vue'

const userStore = useUserStore()
const regionStore = useRegionStore()

export default {
  name: 'Navigation',
  components: {
    ThemeSwitcher,
    Loader,
    ModalLoader,
    DonationModal,
    PetitionBanner,
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
    const basketStore = useBasketStore()
    return {
      userStore,
      basketStore,
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
    useRestrictedNavigation () {
      return userStore.isApiRestrictedForLegalReasons
    },
    isFoodsaver () {
      return userStore.isFoodsaver
    },
    userId () {
      return userStore.getUserId
    },
  },
  watch: {
    isFoodsaver: {
      async handler (newValue) {
        if (newValue && !this.useRestrictedNavigation) {
          await DataStores.mutations.fetch(false, this.userId)
        }
      },
      immediate: true,
      deep: true,
    },
  },
  async created () {
    // Load data
    if (this.isLoggedIn && !this.useRestrictedNavigation) {
      // TODO: NO APIS :(
      DataGroups.mutations.set(this.groups)
      regionStore.regions = this.regions
      await this.basketStore.fetchOwn()
      await DataBells.mutations.fetch()
      await DataConversations.initConversations()
    }
  },
  async mounted () {
    window.addEventListener('resize', this.resizeHandler)
    window.addEventListener('load', this.resizeHandler)
    if (userStore.hasMailBox && !this.useRestrictedNavigation) {
      userStore.fetchMailUnreadCount()
    }
  },
  methods: {
    resizeHandler () {
      const height = this.$refs.navbar.$el.getBoundingClientRect().height + 'px'
      document.documentElement.style.setProperty('--navbar-height', height)
    },
    async deleteCaches () {
      await clearCaches()
      channel.postMessage({ type: BROADCAST_TYPE.LOGOUT })
      window.location.href = this.$url('logout')
    },
  },
}
</script>
