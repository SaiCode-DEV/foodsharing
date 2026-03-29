<template>
  <div>
    <div v-if="isLoading" class="text-center p-4">
      <b-spinner />
    </div>

    <div v-else-if="!profileData || Object.keys(profileData).length === 0">
      <b-alert show variant="warning">
        <h4>{{ $t('settings.no_rights') }}</h4>
      </b-alert>
    </div>

    <TabbedPage v-else>
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
        v-if="isMe && isFoodsaver && profileData?.businessCardData !== undefined"
        :title="$t('settings.businesscard')"
        :active="subPage === SUB_PAGE.BUSINESS_CARD"
      >
        <BusinessCard :business-card-data="profileData?.businessCardData" />
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
        v-if="isMe && profileData?.sleepingData !== undefined"
        :title="$t('settings.sleep.title')"
        :active="subPage === SUB_PAGE.SLEEPING"
      >
        <SleepingMode
          :sleep-data="profileData?.sleepingData"
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
        <Quiz :quiz-id="profileData?.targetRole" />
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
        <DeleteAccount :user-id="userId" :profile-data="profileData" />
      </ResponsiveTab>
    </TabbedPage>
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
const otherUserProfileData = ref(null)
const isLoading = ref(false)

const isMe = computed(() => userStore.getUserId === userId.value)
const isFoodsaver = computed(() => userStore.isFoodsaver)
const isOrgaUser = computed(() => userStore.isOrga)
const profileData = computed(() => {
  if (userId.value === null) return null
  if (isMe.value) return userStore.getUserSettings
  if (Object.keys(otherUserProfileData.value ?? {}).length > 0) {
    return otherUserProfileData.value
  }
  return null
})

const targetRole = computed(() => profileData.value?.targetRole ?? null)
const showQuiz = computed(() => {
  if (targetRole.value === null) return false
  if (targetRole.value === 3) return /show-bot-quiz/.test(location.search)
  return true
})
const getQuizTranslation = computed(() => {
  if (targetRole.value !== null) {
    return i18n?.('settings.quiz.' + targetRole.value) ?? ''
  }
  return null
})

onMounted(async () => {
  const match = window.location.pathname.match(/\/user\/(\d+)\/settings/)
  userId.value = match ? Number(match[1]) : undefined

  // Load profile data
  isLoading.value = true
  if (isMe.value) {
    await userStore.fetchProfileSettings()
  } else {
    try {
      otherUserProfileData.value = await getUserProfileSettings(userId.value)
    } catch (error) {
      console.error('Error fetching other user profile data:', error)
      otherUserProfileData.value = {}
    }
  }
  isLoading.value = false
})
</script>

<style lang="scss" scoped>
// Custom styles can be added here if needed
</style>
