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
import { ref, computed, watch, defineProps, onMounted, onBeforeMount } from 'vue'
import { useUserStore } from '@/stores/user.js'
import { useRegionStore } from '@/stores/regions.js'
import { useDonationStore } from '@/stores/donation'
import DataBells from '@/stores/bells.js'
import DataStores from '@/stores/stores.js'
import DataConversations from '@/stores/conversations.js'
import DataGroups from '@/stores/groups.js'
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
import Loader from './Loader.vue'
import PetitionBanner from '@/views/partials/TopBanner/Petition/PetitionBanner.vue'
import ConfirmationDialogue from '@/components/UI/ConfirmationDialogue.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { useMediaQuery } from '@/composables/useMediaQuery'
import ChatDock from '@/components/Chat/ChatDock.vue'
import DonationButton from '@/components/DonationButton.vue'

const props = defineProps({
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
const navbar = ref(null)
const confirmDialog = ref(null)

const isLoggedIn = computed(() => userStore.isLoggedIn)
const isFoodsaver = computed(() => userStore.isFoodsaver)
const userId = computed(() => userStore.getUserId)

// TODO: Decide how to handle this in the future! Emulating the old behavior for now
const widthAllowsChatDock = computed(() => window.innerWidth >= 900)
const showChatDock = computed(() => isLoggedIn.value && widthAllowsChatDock.value && !location.pathname.startsWith('/msg'))

const { emitter } = useConfirmationDialogue()

watch(isFoodsaver, async (newValue) => {
  if (newValue) {
    await DataStores.mutations.fetch(false, userId.value)
  }
}, { immediate: true, deep: true })

onBeforeMount(async () => {
  if (isLoggedIn.value) {
    DataGroups.mutations.set(props.groups)
    regionStore.regions = props.regions
    await DataBells.mutations.fetch()
    await DataConversations.initConversations()
  }
})

onMounted(() => {
  window.addEventListener('resize', resizeHandler)
  window.addEventListener('load', resizeHandler)
  if (userStore.hasMailBox) {
    userStore.fetchMailUnreadCount()
  }

  emitter.addListener('show-confirmation', (options) => {
    confirmDialog.value?.show(options)
  })

  donationStore.fetchDonationData()
})

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
