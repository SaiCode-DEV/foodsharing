<template>
  <div>
    <div v-if="userProfileSettings === undefined || userProfileSettings.length <= 0">
      <b-alert show variant="warning">
        <h4>{{ $t('settings.no_rights') }}</h4>
      </b-alert>
    </div>

    <TabbedPage v-else-if="profileData">
      <template #top>
        <b-alert
          v-if="!isMe && profileData"
          show
          variant="warning"
        >
          <h4>{{ $t('settings.edit_other', { name: profileData.firstName }) }}</h4>
        </b-alert>
      </template>

      <ResponsiveTab :title="$t('settings.profile')" :active="subPage === SUB_PAGE.GENERAL">
        <ProfileSettings :profile-data="profileData" />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe"
        :title="$t('settings.notifications')"
        :active="subPage === SUB_PAGE.NOTIFICATION"
      >
        <Notifications />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe && isFoodsaver && userStore.settings.businessCardData !== undefined"
        :title="$t('settings.businesscard')"
        :active="subPage === SUB_PAGE.BUSINESS_CARD"
      >
        <BusinessCard :business-card-data="userStore.settings.businessCardData" />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe"
        :title="$t('settings.calendar.menu')"
        :active="subPage === SUB_PAGE.CALENDAR"
      >
        <Calendar />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isFoodsaver && isMe"
        :title="$t('settings.passport.menu')"
        :active="subPage === SUB_PAGE.PASSPORT"
      >
        <Passport />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe && userStore.settings.sleepingData !== undefined"
        :title="$t('settings.sleep.title')"
        :active="subPage === SUB_PAGE.SLEEPING"
      >
        <SleepingMode
          :sleep-status="userStore.settings.sleepingData.sleep_status"
          :sleep-from="userStore.settings.sleepingData.sleep_from"
          :sleep-until="userStore.settings.sleepingData.sleep_until"
          :sleep-message="userStore.settings.sleepingData.sleep_msg"
        />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe || isOrgaUser"
        :title="$t('settings.account_security.title')"
        :active="subPage === SUB_PAGE.ACCOUNT_SECURITY"
      >
        <AccountSecurity :profile-data="profileData" />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="showQuiz"
        :title="getQuizTranslation"
        :active="subPage === SUB_PAGE.QUIZ"
      >
        <Quiz :quiz-id="userStore.settings.targetRole" />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe"
        ref="hygieneTab"
        :title="$t('terminology.hygiene_training')"
        :active="subPage === SUB_PAGE.HYGIENE"
      >
        <Quiz :quiz-id="4" />
      </ResponsiveTab>
      <ResponsiveTab
        v-if="isMe || isOrgaUser"
        :title="$t('foodsaver.delete_account')"
        :active="subPage === SUB_PAGE.DELETE_ACCOUNT"
      >
        <DeleteAccount :user-id="userId" />
      </ResponsiveTab>
    </TabbedPage>

    <div v-else class="text-center p-4">
      <b-spinner />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineProps } from 'vue'
import TabbedPage from '@/views/pages/Layout/TabbedPage.vue'
import ResponsiveTab from '@/components/TabView/ResponsiveTab.vue'
import Notifications from './Notifications.vue'
import Calendar from './Calendar.vue'
import Passport from '@/components/Settings/Passport.vue'
import SleepingMode from './SleepingMode.vue'
import AccountSecurity from '@/components/Settings/AccountSecurity.vue'
import DeleteAccount from './DeleteAccount.vue'
import BusinessCard from '../../BusinessCard/components/BusinessCard.vue'
import ProfileSettings from './ProfileSettings.vue'
import Quiz from '@/views/pages/Quiz/Quiz.vue'
import { useUserStore } from '@/stores/user'
import { getUserProfileSettings } from '@/api/user'
import { SUB_PAGE } from '@/stores/settings'
import i18n from '@/helper/i18n'

defineProps({
  subPage: { type: String, default: null },
})

const userStore = useUserStore()
const userId = ref(null)
const profileData = ref(null)

const getQuizTranslation = computed(() => {
  if (userStore.settings.targetRole !== null) {
    return i18n?.('settings.quiz.' + userStore.settings.targetRole) ?? ''
  }
  return null
})

const sessionUserId = computed(() => userStore.getUserId)
const userProfileSettings = computed(() => userStore.getUserSettings)
const isMe = computed(() => sessionUserId.value === userId.value)
const isFoodsaver = computed(() => userStore.isFoodsaver)
const isOrgaUser = computed(() => userStore.isOrga)
const showQuiz = computed(() => {
  // userStore.settings.targetRole can be undefined before the data was loaded
  if (userStore.settings.targetRole == null) return false
  if (userStore.settings.targetRole === 3) return /show-bot-quiz/.test(location.search)
  return true
})

onMounted(async () => {
  const match = window.location.pathname.match(/\/user\/(\d+)\/settings/)
  userId.value = match ? Number(match[1]) : undefined

  // Load profile data
  if (isMe.value) {
    await userStore.fetchProfileSettings()
    profileData.value = userStore.settings
  } else {
    profileData.value = await getUserProfileSettings(userId.value)
  }
})
</script>

<style lang="scss" scoped>
// Custom styles can be added here if needed
</style>
