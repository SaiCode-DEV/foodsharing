<template>
  <div>
    <div v-if="userProfileSettings === undefined || userProfileSettings.length <= 0">
      <b-alert show variant="warning">
        <h4>{{ $i18n('settings.no_rights') }}</h4>
      </b-alert>
    </div>

    <b-card v-else no-body>
      <b-tabs
        card
        :vertical="!viewIsMobile"
      >
        <b-tab :title="$i18n('settings.title')" :active="subPage === SUB_PAGE.GENERAL">
          <ProfileSettings />
        </b-tab>
        <b-tab
          v-if="isMe"
          lazy
          :title="$i18n('settings.notifications')"
          :active="subPage === SUB_PAGE.NOTIFICATION"
        >
          <Notifications />
        </b-tab>
        <b-tab
          v-if="isMe && isFoodsaver && userStore.settings.businessCardData !== undefined"
          :title="$i18n('settings.businesscard')"
          :active="subPage === SUB_PAGE.BUSINESS_CARD"
        >
          <BusinessCard :business-card-data="userStore.settings.businessCardData" />
        </b-tab>
        <b-tab
          v-if="isMe"
          :title="$i18n('settings.calendar.menu')"
          :active="subPage === SUB_PAGE.CALENDAR"
        >
          <Calendar />
        </b-tab>
        <b-tab
          v-if="isFoodsaver && isMe"
          :title="$i18n('settings.passport.menu')"
          :active="subPage === SUB_PAGE.PASSPORT"
        >
          <Passport />
        </b-tab>
        <b-tab
          v-if="isMe && userStore.settings.sleepingData !== undefined"
          :title="$i18n('settings.sleep.title')"
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
          :title="$i18n('settings.email')"
          :active="subPage === SUB_PAGE.CHANGE_EMAIL"
        >
          <ChangeEmailForm :is-me="isMe" :user-id="userId" />
          <hr class="my-3">
          <ChangePasswordForm v-if="isMe" />
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
          :title="$i18n('terminology.hygiene_training')"
          :active="subPage === SUB_PAGE.HYGIENE"
        >
          <Quiz :quiz-id="4" />
        </b-tab>
        <b-tab
          v-if="isMe || isOrgaUser"
          :title="$i18n('foodsaver.delete_account')"
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
