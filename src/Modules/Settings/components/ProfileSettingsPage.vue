<template>
  <div>
    <div v-if="userDetails === undefined || userDetails.length <= 0">
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
          <ProfileSettings :user-details="userDetails" :permissions="permissions" />
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
          v-if="isMe && isFoodsaver"
          :title="$i18n('settings.businesscard')"
          :active="subPage === SUB_PAGE.BUSINESS_CARD"
        >
          <BusinessCard :business-card-data="businessCardData" />
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
          v-if="isMe"
          :title="$i18n('settings.sleep.title')"
          :active="subPage === SUB_PAGE.SLEEPING"
        >
          <SleepingMode
            :sleep-status="sleepingData.sleep_status"
            :sleep-from="sleepingData.sleep_from"
            :sleep-until="sleepingData.sleep_until"
            :sleep-message="sleepingData.sleep_msg"
          />
        </b-tab>
        <b-tab
          v-if="isMe || permissions.mayChangeEmailImmediately"
          :title="$i18n('settings.email')"
          :active="subPage === SUB_PAGE.CHANGE_EMAIL"
        >
          <ChangeEmailForm :is-me="isMe" :user-id="userDetails.id" />
          <hr class="my-3">
          <ChangePasswordForm v-if="isMe" />
        </b-tab>
        <b-tab
          v-if="showQuiz"
          :title="getQuizTranslation"
          :active="subPage === SUB_PAGE.QUIZ"
        >
          <Quiz :quiz-id="targetRole" />
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
          <DeleteAccount :user-id="userDetails.id" />
        </b-tab>
      </b-tabs>
    </b-card>
  </div>
</template>

<script>
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

const userStore = useUserStore()

export default {
  name: 'ProfileSettingsPage',
  components: {
    ProfileSettings,
    Notifications,
    Calendar,
    Passport,
    SleepingMode,
    ChangeEmailForm,
    ChangePasswordForm,
    DeleteAccount,
    BusinessCard,
    Quiz,
  },
  mixins: [MediaQueryMixin],
  props: {
    userDetails: { type: Object, default: () => {} },
    sleepingData: { type: Object, default: () => {} },
    businessCardData: { type: Object, default: () => {} },
    permissions: {
      type: Object,
      default: () => {
        return {
          mayChangeEmailImmediately: false,
          mayChangeVerifiedData: false,
          isOnTeamPage: false,
        }
      },
    },
    targetRole: { type: Number, default: null },
    subPage: { type: String, default: null },
  },
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    getQuizTranslation () {
      if (this.targetRole !== null) {
        return this.$i18n('settings.quiz.' + this.targetRole)
      }
      return null
    },
    isMe () {
      return userStore.getUserId === this.userDetails.id
    },
    isFoodsaver () {
      return userStore.isFoodsaver
    },
    isOrgaUser () {
      return userStore.isOrga
    },
    SUB_PAGE () {
      return SUB_PAGE
    },
    showQuiz () {
      if (this.targetRole === null) return false
      if (this.targetRole === 3) return /show-bot-quiz/.test(location.search) // Hide ambassador quiz
      return true
    },
  },
  async mounted () {
    if (!this.isMe) return
    if (this.subPage === SUB_PAGE.HYGIENE) {
      await this.$nextTick()
      this.$refs.hygieneTab.activate()
    }
  },
}
</script>

<style lang="scss" scoped>
.nav-tabs.flex-column .nav-link {
  border-radius: var(--border-radius);
}
</style>
