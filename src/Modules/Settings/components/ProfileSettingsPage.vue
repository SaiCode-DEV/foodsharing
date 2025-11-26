<template>
  <div>
    <div v-if="userProfileSettings === undefined || userProfileSettings.length <= 0">
      <b-alert show variant="warning">
        <h4>{{ $t('settings.no_rights') }}</h4>
      </b-alert>
    </div>

    <b-card v-else no-body>
      <b-tabs
        card
        :vertical="!viewIsMobile"
      >
        <b-tab :title="$t('settings.title')" :active="subPage === SUB_PAGE.GENERAL">
          <ProfileSettings />
        </b-tab>
        <b-tab
          v-if="isMe"
          lazy
          :title="$t('settings.notifications')"
          :active="subPage === SUB_PAGE.NOTIFICATION"
        >
          <Notifications />
        </b-tab>
        <b-tab
          v-if="isMe && isFoodsaver && userStore.settings.businessCardData !== undefined"
          :title="$t('settings.businesscard')"
          :active="subPage === SUB_PAGE.BUSINESS_CARD"
        >
          <BusinessCard :business-card-data="userStore.settings.businessCardData" />
        </b-tab>
        <b-tab
          v-if="isMe"
          :title="$t('settings.calendar.menu')"
          :active="subPage === SUB_PAGE.CALENDAR"
        >
          <Calendar />
        </b-tab>
        <b-tab
          v-if="isFoodsaver && isMe"
          :title="$t('settings.passport.menu')"
          :active="subPage === SUB_PAGE.PASSPORT"
        >
          <Passport />
        </b-tab>
        <b-tab
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
        </b-tab>
        <b-tab
          v-if="isMe || userStore.settings.mayChangeEmailImmediately"
          :title="$t('settings.email')"
          :active="subPage === SUB_PAGE.CHANGE_EMAIL"
        >
          <ChangeEmailForm :is-me="isMe" :user-id="userId" />
          <hr class="my-3">
          <ChangePasswordForm v-if="isMe" />
        </b-tab>
        <b-tab
          v-if="isMe"
          :title="$t('settings.2fa.title')"
          :active="subPage === SUB_PAGE.CHANGE_2FA"
        >
          <Change2FAForm
            v-if="userStore.settings.twoFactorEnabled !== undefined"
            :totp-active="userStore.settings.twoFactorEnabled"
            :num-backup-codes="userStore.settings.numBackupCodes"
          />
        </b-tab>
        <b-tab
          v-if="showQuiz"
          :title="getQuizTranslation"
          :active="subPage === SUB_PAGE.QUIZ"
        >
          <Quiz :quiz-id="userStore.settings.targetRole" />
        </b-tab>
        <b-tab
          v-if="isMe"
          ref="hygieneTab"
          :title="$t('terminology.hygiene_training')"
          :active="subPage === SUB_PAGE.HYGIENE"
        >
          <Quiz :quiz-id="4" />
        </b-tab>
        <b-tab
          v-if="isMe || isOrgaUser"
          :title="$t('foodsaver.delete_account')"
          :active="subPage === SUB_PAGE.DELETE_ACCOUNT"
        >
          <DeleteAccount :user-id="userId" />
        </b-tab>
      </b-tabs>
    </b-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, defineProps } from 'vue'
import Notifications from './Notifications.vue'
import Calendar from './Calendar.vue'
import Passport from '@/components/Settings/Passport.vue'
import SleepingMode from './SleepingMode.vue'
import ChangeEmailForm from './ChangeEmailForm.vue'
import Change2FAForm from './Change2FAForm.vue'
import ChangePasswordForm from './ChangePasswordForm.vue'
import DeleteAccount from './DeleteAccount.vue'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import BusinessCard from '../../BusinessCard/components/BusinessCard.vue'
import ProfileSettings from './ProfileSettings.vue'
import Quiz from '@/views/pages/Quiz/Quiz.vue'
import { useUserStore } from '@/stores/user'
import { SUB_PAGE } from '@/stores/settings'
import i18n from '@/helper/i18n'

const props = defineProps({
  subPage: { type: String, default: null },
})

const userStore = useUserStore()
const userId = ref(null)
const viewIsMobile = MediaQueryMixin.computed?.viewIsMobile?.call({}) ?? false // fallback, falls Mixin nicht als Funktion nutzbar

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

  // Load profile settings early if this is the current user
  if (isMe.value) {
    await userStore.fetchProfileSettings()
  }

  if (props.subPage === SUB_PAGE.HYGIENE) {
    await nextTick()
    // HygieneTab aktivieren
    if (globalThis.$refs?.hygieneTab?.activate) {
      globalThis.$refs.hygieneTab.activate()
    }
  }
})
</script>

<style lang="scss" scoped>
.nav-tabs.flex-column .nav-link {
  border-radius: var(--border-radius);
}
</style>
