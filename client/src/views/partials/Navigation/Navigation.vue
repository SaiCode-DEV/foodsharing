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
    <PetitionBanner />
    <div class="metanav-container container">
      <MetaNavLoggedIn v-if="!mobile && isLoggedIn" />
      <MetaNavLoggedOut v-else-if="!mobile" />
      <DonationButton v-if="!mobile" :button-link="$url('donations')" />
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

    <ConfirmationDialogue ref="confirmDialog" />
    <ChatDock v-if="showChatDock" />
  </b-navbar>
</template>

<script setup>
import { ref, computed, watch, defineProps, onMounted, onBeforeMount, onBeforeUnmount } from 'vue'
import { useUserStore } from '@/stores/user'
import { useRegionStore } from '@/stores/regions'
import { useDonationStore } from '@/stores/donation'
import DataBells from '@/stores/bells'
import DataStores from '@/stores/stores'
import DataConversations from '@/stores/conversations'
import DataGroups from '@/stores/groups'
// States
import MetaNavLoggedIn from './States/MetaNav/LoggedIn.vue'
import MetaNavLoggedOut from './States/MetaNav/LoggedOut.vue'
import MainNavLoggedIn from './States/MainNav/LoggedIn.vue'
import MainNavLoggedOut from './States/MainNav/LoggedOut.vue'
import SideNavLoggedIn from './States/SideNav/LoggedIn.vue'
import SideNavLoggedOut from './States/SideNav/LoggedOut.vue'
import DonationButton from '@/components/DonationButton.vue'
// ModalLoader
import ModalLoader from '@/views/partials/Modals/ModalLoader.vue'
import DonationModal from '@/components/Modals/Donation/DonationModal.vue'
import ThemeSwitcherModal from '@/views/partials/Modals/ThemeSwitcherModal.vue'
// Mixins
import Loader from './Loader.vue'
import PetitionBanner from '@/views/partials/TopBanner/Petition/PetitionBanner.vue'
import ConfirmationDialogue from '@/components/UI/ConfirmationDialogue.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { useRoute } from 'vue-router/composables'
import ChatDock from '@/components/Chat/ChatDock.vue'
import serverData from '@/helper/server-data'
import { get } from '@/api/base'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'

defineProps({
  regions: {
    type: Array,
    default: () => [],
  },
  groups: {
    type: Array,
    default: () => [],
  },
})

const userStore = useUserStore()
const regionStore = useRegionStore()
const donationStore = useDonationStore()

const { mobile } = useMediaQuery()
const route = useRoute()
const navbar = ref(null)
const confirmDialog = ref(null)

const isLoggedIn = computed(() => userStore.isLoggedIn)
const isFoodsaver = computed(() => userStore.isFoodsaver)
const userId = computed(() => userStore.getUserId)

// TODO: Decide how to handle this in the future! Emulating the old behavior for now
const widthAllowsChatDock = computed(() => window.innerWidth >= 900)
// The route, not `location.pathname`: the navigation outlives every client side
// navigation, so a plain read would keep the answer of the page that was loaded
// as a document. The dock would then stay alive on the message page and fight
// its chat component over the shared conversation store.
const showChatDock = computed(() => isLoggedIn.value && widthAllowsChatDock.value && !route.path.startsWith('/msg'))

const { emitter } = useConfirmationDialogue()

watch(isFoodsaver, async (newValue) => {
  if (newValue) {
    await DataStores.mutations.fetch(false, userId.value)
  }
}, { immediate: true, deep: true })

onBeforeMount(async () => {
  DataGroups.mutations.set(serverData.groups || [])
  regionStore.regions = serverData.regions || []
  if (isLoggedIn.value) {
    await DataBells.mutations.fetch()
    await DataConversations.initConversations()
  }
})

onMounted(() => {
  resizeHandler()
  window.addEventListener('resize', resizeHandler)
  window.addEventListener('load', resizeHandler)
  channel.addEventListener?.('message', updateMailUnreadCount)
  if (userStore.hasMailBox) {
    userStore.fetchMailUnreadCount(true)
  }

  emitter.addListener('show-confirmation', (options) => {
    confirmDialog.value?.show(options)
  })

  donationStore.fetchDonationData()
})

onBeforeUnmount(() => {
  channel.removeEventListener?.('message', updateMailUnreadCount)
})

function updateMailUnreadCount (event) {
  if (event.data.type === BROADCAST_TYPE.UPDATE_MAIL_UNREAD_COUNT) {
    userStore.mailUnreadCount = event.data.unreadCount
  }
}

function resizeHandler () {
  const height = navbar.value.$el.getBoundingClientRect().height + 'px'
  document.documentElement.style.setProperty('--navbar-height', height)
}
</script>

<style lang="scss" scoped>
.navigation.navbar .metanav {
  padding: 0.5rem 0.5rem;
}
</style>
