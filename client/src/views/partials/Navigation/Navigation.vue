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
    <DonationModal v-if="!useRestrictedNavigation" />
    <PetitionBanner v-if="!useRestrictedNavigation" />
    <div class="metanav-container container">
      <MetaNavLoggedIn v-if="!mobile && isLoggedIn" />
      <MetaNavLoggedOut v-else-if="!mobile" />
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

    <ConfirmationDialogue ref="confirmDialog" />
    <Notifications />
    <ChatDock v-if="isLoggedIn && !useRestrictedNavigation" />
  </b-navbar>
</template>

<script setup>
import { ref, computed, watch, defineProps, onMounted, onBeforeMount } from 'vue'
import { useUserStore } from '@/stores/user.js'
import { useRegionStore } from '@/stores/regions.js'
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
import { clearCaches } from '@/helper/cache'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'
import ThemeSwitcher from '@/components/ThemeSwitcher.vue'
import ConfirmationDialogue from '@/components/UI/ConfirmationDialogue.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import Notifications from '@/components/UI/Notifications.vue'
import { useMediaQuery } from '@/composables/useMediaQuery'
import ChatDock from '@/components/Chat/ChatDock.vue'

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

const { mobile } = useMediaQuery()
const navbar = ref(null)
const confirmDialog = ref(null)

const isLoggedIn = computed(() => userStore.isLoggedIn)
const isFoodsaver = computed(() => userStore.isFoodsaver)
const userId = computed(() => userStore.getUserId)
const useRestrictedNavigation = computed(() => userStore.isApiRestrictedForLegalReasons)

const { emitter } = useConfirmationDialogue()

watch(isFoodsaver, async (newValue) => {
  if (newValue && !useRestrictedNavigation.value) {
    await DataStores.mutations.fetch(false, userId.value)
  }
}, { immediate: true, deep: true })

onBeforeMount(async () => {
  if (isLoggedIn.value && !useRestrictedNavigation.value) {
    DataGroups.mutations.set(props.groups)
    regionStore.regions = props.regions
    await DataBells.mutations.fetch()
    await DataConversations.initConversations()
  }
})

onMounted(() => {
  window.addEventListener('resize', resizeHandler)
  window.addEventListener('load', resizeHandler)
  if (userStore.hasMailBox && !useRestrictedNavigation.value) {
    userStore.fetchMailUnreadCount()
  }

  emitter.addListener('show-confirmation', (options) => {
    confirmDialog.value?.show(options)
  })
})

function resizeHandler () {
  const height = navbar.value.$el.getBoundingClientRect().height + 'px'
  document.documentElement.style.setProperty('--navbar-height', height)
}

async function deleteCaches () {
  await clearCaches()
  channel.postMessage({ type: BROADCAST_TYPE.LOGOUT })
  window.location.href = '/logout'
}
</script>
