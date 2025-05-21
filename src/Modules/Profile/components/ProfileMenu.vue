<template>
  <div>
    <div class="mb-2 text-center">
      <Avatar
        :user="{ avatar: profileMenu.photo, isSleeping: profileMenu.isSleeping }"
        :size="130"
      />
    </div>
    <div
      v-if="profileMenu.isOnline"
      class="alert alert-info text-center"
      role="alert"
    >
      <i class="fas fa-circle text-secondary" />
      {{ $i18n('profile.online', { name: profileMenu.foodSaverName }) }}
    </div>
    <b-list-group>
      <b-list-group-item
        class="list-group-item list-group-item-action text-center"
      >
        <h3>{{ profileMenu.foodSaverName }}</h3>
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.mayAdmin || profileMenu.fsId === profileMenu.fsIdSession"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('settings', profileMenu.fsId)"
      >
        <i class="fas fa-pencil-alt fa-fw" /> {{ $i18n('settings.header') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.fsId !== profileMenu.fsIdSession"
        type="button"
        class="list-group-item list-group-item-action"
        @click="openChat(profileMenu.fsId)"
      >
        <i class="fas fa-comment fa-fw" /> {{ $i18n('chat.open_chat') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.fsId !== profileMenu.fsIdSession && buddyType === buddyTypes.NO_BUDDY"
        type="button"
        class="list-group-item list-group-item-action"
        :disabled="loading"
        @click="sendBuddyRequest(profileMenu.fsId)"
      >
        <i class="fas fa-user-friends fa-fw" /> {{ $i18n('profile.nav.buddy', { name: profileMenu.foodSaverName }) }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.fsId !== profileMenu.fsIdSession && buddyType !== buddyTypes.NO_BUDDY"
        type="button"
        class="list-group-item list-group-item-action"
        :disabled="loading"
        @click="removeBuddy(profileMenu.fsId)"
      >
        <i class="fas fa-user-slash fa-fw" /> {{ $i18n('profile.nav.remove_buddy', { name: profileMenu.foodSaverName }) }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.mayHistory"
        type="button"
        class="list-group-item list-group-item-action"
        @click="openHistory(1)"
      >
        <i class="fas fa-file-alt fa-fw" /> {{ $i18n('profile.nav.history') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.mayHistory"
        type="button"
        class="list-group-item list-group-item-action"
        @click="openHistory(0)"
      >
        <i class="fas fa-file-alt fa-fw" /> {{ $i18n('profile.nav.verificationHistory') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.maySeeQuizSessions"
        type="button"
        class="list-group-item list-group-item-action"
        @click="$bvModal.show('quizSessionHistoryModal')"
      >
        <i class="fas fa-file-alt fa-fw" /> {{ $i18n('profile.nav.quizSessionHistory') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.mayNotes"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('profileNotes', fsId)"
      >
        <i class="far fa-file-alt fa-fw" />
        <span>
          {{ $i18n('profile.nav.notes') }} <strong>({{ profileMenu.noteCount }})</strong>
        </span>
      </b-list-group-item>
      <b-list-group-item
        v-if="profileMenu.mayViolation && profileMenu.violationCount > 0"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('violations', profileMenu.fsId)"
      >
        <i class="far fa-meh fa-fw" />
        <span>
          {{ $i18n('profile.nav.violations') }} <strong>({{ profileMenu.violationCount }})</strong>
        </span>
      </b-list-group-item>
      <b-list-group-item
        v-if="showReportButton"
        type="button"
        class="list-group-item list-group-item-action"
        href="#"
        @click="$refs.report_request.show()"
      >
        <i class="fas fa-people-arrows fa-fw" /> {{ profileMenu.buttonNameReportRequest }}
      </b-list-group-item>
      <b-list-group-item
        v-if="showModerationButton"
        type="button"
        class="list-group-item list-group-item-action"
        href="#"
        @click="$refs.modal_mediation.show()"
      >
        <i class="far fa-handshake fa-fw" /> {{ $i18n('profile.mediationRequest') }}
      </b-list-group-item>
    </b-list-group>
    <b-list-group>
      <b-list-group-item
        v-if="profileMenu.fsId === profileMenu.fsIdSession"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('settingsHygiene')"
      >
        <i class="fas fa-hand-sparkles fa-fw" />
        {{ $i18n('terminology.hygiene_training') }}
      </b-list-group-item>
    </b-list-group>
    <b-modal
      v-if="showModerationButton"
      ref="modal_mediation"
      :title="$i18n('profile.mediation.title', { name: profileMenu.foodSaverName })"
      :cancel-title="$i18n('button.cancel')"
      header-class="d-flex"
      content-class="pr-3 pt-3"
    >
      <MediationRequest
        :mediation-group-email="profileMenu.mediationGroupEmail"
        :has-local-mediation-group="profileMenu.hasLocalMediationGroup"
      />
    </b-modal>
    <ReportRequest
      v-if="showReportButton"
      ref="report_request"
      :food-saver-name="profileMenu.foodSaverName"
      :reported-id="profileMenu.fsId"
      :reporter-id="profileMenu.fsIdSession"
      :store-list-options="profileMenu.storeListOptions"
      :has-report-group="profileMenu.hasReportGroup"
      :has-arbitration-group="profileMenu.hasArbitrationGroup"
      :is-reported-id-report-admin="profileMenu.isReportedIdReportAdmin"
      :is-reporter-id-report-admin="profileMenu.isReporterIdReportAdmin"
      :is-reported-id-arbitration-admin="profileMenu.isReportedIdArbitrationAdmin"
      :is-reporter-id-arbitration-admin="profileMenu.isReporterIdArbitrationAdmin"
      :is-report-button-enabled="profileMenu.isReportButtonEnabled"
      :reporter-has-report-group="profileMenu.reporterHasReportGroup"
      :reason-option-settings="profileMenu.reasonOptionSettings"
      :reason-option-other="profileMenu.reasonOptionOther"
      :mailbox-name-report="profileMenu.mailboxNameReportRequest"
      :mailbox-name-arbitration="profileMenu.mailboxNameArbitrationRequest"
    />
    <ProfileHistoryModal ref="profileHistoryModal" />
    <QuizSessionHistoryModal :foodsaver-id="profileMenu.fsId" />
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import { pulseError, pulseInfo } from '@/script'
import conversationStore from '@/stores/conversations'
import MediationRequest from './MediationRequest'
import ReportRequest from './ReportRequest'
import ProfileHistoryModal from './ProfileHistoryModal'
import { sendBuddyRequest, removeBuddy } from '@/api/buddy'
import i18n from '@/helper/i18n'
import QuizSessionHistoryModal from './QuizSessionHistoryModal.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

const BUDDY_TYPES = Object.freeze({
  NO_BUDDY: -1,
  REQUESTED: 0,
  BUDDY: 1,
})

export default {
  components: { Avatar, ReportRequest, MediationRequest, ProfileHistoryModal, QuizSessionHistoryModal },
  props: {
    profileMenu: { type: Object, required: true },
    currentUserId: { type: Number, default: null },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      buddyType: this.profileMenu.initialBuddyType,
      buddyTypes: BUDDY_TYPES,
      loading: false,
    }
  },
  computed: {
    showReportButton () {
      return this.profileMenu.buttonNameReportRequest !== null && this.profileMenu.buttonNameReportRequest.length > 0
    },
    showModerationButton () {
      return this.fsId !== this.currentUserId
    },
  },
  methods: {
    openChat (fsId) {
      conversationStore.openChatWithUser(fsId)
    },
    async sendBuddyRequest (userId) {
      const dialogueOptions = {
        title: this.$i18n('buddy.send.confirm_title', { name: this.profileMenu.foodSaverName }),
        okTitle: this.$i18n('yes'),
        okVariant: undefined,
      }
      if (!await this.confirmationDialogue('buddy.send.confirm_text', dialogueOptions)) return
      this.loading = true
      try {
        const request = await sendBuddyRequest(userId)
        if (request.isBuddy) {
          pulseInfo(i18n('buddy.request_accepted'))
          this.buddyType = BUDDY_TYPES.BUDDY
        } else {
          pulseInfo(i18n('buddy.request_sent'))
          this.buddyType = BUDDY_TYPES.REQUESTED
        }
      } catch (err) {
        pulseError(i18n('error_unexpected'))
        this.buddyType = BUDDY_TYPES.NO_BUDDY
      }
      this.loading = false
    },
    async removeBuddy (userId) {
      const dialogueOptions = {
        title: this.$i18n('buddy.remove.confirm_title', { name: this.profileMenu.foodSaverName }),
        okTitle: this.$i18n('yes'),
      }
      if (!await this.confirmationDialogue('buddy.remove.confirm_text', dialogueOptions)) return
      this.loading = true
      try {
        await removeBuddy(userId)
        this.buddyType = BUDDY_TYPES.NO_BUDDY
      } catch (err) {
        pulseError(i18n('error_unexpected'))
        this.buddyType = BUDDY_TYPES.REQUESTED
      }
      this.loading = false
    },
    openHistory (type) {
      this.$refs.profileHistoryModal.showModal(this.profileMenu.fsId, type === 0)
    },
  },
}
</script>

<style lang="scss" scoped>
.list-group-item:not(:last-child) {
  border-bottom: 0;
}

.list-group-item {
  border: none;
  padding: 0.45rem 1.25rem;
}
</style>
