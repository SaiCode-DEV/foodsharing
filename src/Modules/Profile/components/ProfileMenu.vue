<template>
  <div>
    <div
      class="mb-2 text-center"
    >
      <Avatar
        :url="photo"
        :is-sleeping="isSleeping"
        :size="130"
        :auto-scale="true"
      />
    </div>
    <div
      v-if="isOnline"
      class="alert alert-info text-center"
      role="alert"
    >
      <i class="fas fa-circle text-secondary" />
      {{ $i18n('profile.online', { name: foodSaverName }) }}
    </div>
    <b-list-group>
      <b-list-group-item
        v-if="fsId === fsIdSession"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('settings')"
      >
        <i class="fas fa-pencil-alt fa-fw" /> {{ $i18n('settings.header') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="fsId !== fsIdSession"
        type="button"
        class="list-group-item list-group-item-action"
        @click="openChat(fsId)"
      >
        <i class="fas fa-comment fa-fw" /> {{ $i18n('chat.open_chat') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="mayAdmin"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('foodsaverEdit', fsId)"
      >
        <i class="fas fa-pencil-alt fa-fw" /> {{ $i18n('profile.nav.edit') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="fsId !== fsIdSession && buddyType === buddyTypes.NO_BUDDY"
        type="button"
        class="list-group-item list-group-item-action"
        :disabled="loading"
        @click="sendBuddyRequest(fsId)"
      >
        <i class="fas fa-user-friends fa-fw" /> {{ $i18n('profile.nav.buddy', { name: foodSaverName }) }}
      </b-list-group-item>
      <b-list-group-item
        v-if="fsId !== fsIdSession && buddyType !== buddyTypes.NO_BUDDY"
        type="button"
        class="list-group-item list-group-item-action"
        :disabled="loading"
        @click="removeBuddy(fsId)"
      >
        <i class="fas fa-user-slash fa-fw" /> {{ $i18n('profile.nav.remove_buddy', { name: foodSaverName }) }}
      </b-list-group-item>
      <b-list-group-item
        v-if="mayHistory"
        type="button"
        class="list-group-item list-group-item-action"
        href="#"
        @click="OpenHistory(1)"
      >
        <i class="fas fa-file-alt fa-fw" /> {{ $i18n('profile.nav.history') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="mayHistory"
        type="button"
        class="list-group-item list-group-item-action"
        href="#"
        @click="OpenHistory(0)"
      >
        <i class="fas fa-file-alt fa-fw" /> {{ $i18n('profile.nav.verificationHistory') }}
      </b-list-group-item>
      <b-list-group-item
        v-if="mayNotes"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('profileNotes', fsId)"
      >
        <i class="far fa-file-alt fa-fw" />
        <span v-html="$i18n('profile.nav.notes', { count: noteCount })" />
      </b-list-group-item>
      <b-list-group-item
        v-if="mayViolation && violationCount > 0"
        type="button"
        class="list-group-item list-group-item-action"
        :href="$url('violations', fsId)"
      >
        <i class="far fa-meh fa-fw" />
        <span v-html="$i18n('profile.nav.violations', { count: violationCount })" />
      </b-list-group-item>
      <b-list-group-item
        v-if="showReportButton"
        type="button"
        class="list-group-item list-group-item-action"
        href="#"
        @click="$refs.report_request.show()"
      >
        <i class="far fa-life-ring fa-fw" /> {{ buttonNameReportRequest }}
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
    <b-modal
      v-if="showModerationButton"
      ref="modal_mediation"
      :title="$i18n('profile.mediation.title', { name: foodSaverName })"
      :cancel-title="$i18n('button.cancel')"
      header-class="d-flex"
      content-class="pr-3 pt-3"
    >
      <MediationRequest
        :mediation-group-email="mediationGroupEmail"
        :has-local-mediation-group="hasLocalMediationGroup"
      />
    </b-modal>
    <ReportRequest
      v-if="showReportButton"
      ref="report_request"
      :food-saver-name="foodSaverName"
      :reported-id="fsId"
      :reporter-id="fsIdSession"
      :store-list-options="storeListOptions"
      :has-report-group="hasReportGroup"
      :has-arbitration-group="hasArbitrationGroup"
      :is-reported-id-report-admin="isReportedIdReportAdmin"
      :is-reporter-id-report-admin="isReporterIdReportAdmin"
      :is-reported-id-arbitration-admin="isReportedIdArbitrationAdmin"
      :is-reporter-id-arbitration-admin="isReporterIdArbitrationAdmin"
      :is-report-button-enabled="isReportButtonEnabled"
      :reporter-has-report-group="reporterHasReportGroup"
      :mailbox-name="mailboxNameReportRequest"
    />
    <ProfileHistoryModal
      ref="profileHistoryModal"
    />
  </div>
</template>

<script>
import Avatar from '@/components/Avatar.vue'
import { pulseError, pulseInfo } from '@/script'
import conversationStore from '@/stores/conversations'
import MediationRequest from './MediationRequest'
import ReportRequest from './ReportRequest'
import ProfileHistoryModal from './ProfileHistoryModal'
import { sendBuddyRequest, removeBuddy } from '@/api/buddy'
import i18n from '@/helper/i18n'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

const BUDDY_TYPES = Object.freeze({
  NO_BUDDY: -1,
  REQUESTED: 0,
  BUDDY: 1,
})

export default {
  components: { Avatar, ReportRequest, MediationRequest, ProfileHistoryModal },
  mixins: [ConfirmationDialogue],
  props: {
    fsId: { type: Number, required: true },
    fsIdSession: { type: Number, required: true },
    photo: { type: String, default: '' },
    isSleeping: { type: Boolean, default: false },
    isOnline: { type: Boolean, default: false },
    foodSaverName: { type: String, default: '' },
    initialBuddyType: { type: Number, default: BUDDY_TYPES.NO_BUDDY },
    mayAdmin: { type: Boolean, default: false },
    mayHistory: { type: Boolean, default: false },
    noteCount: { type: Number, default: 0 },
    mayNotes: { type: Boolean, default: false },
    violationCount: { type: Number, default: 0 },
    mayViolation: { type: Boolean, default: false },
    mediationGroupEmail: { type: String, default: '' },
    hasLocalMediationGroup: { type: Boolean, default: false },
    buttonNameReportRequest: { type: String, default: '' },
    storeListOptions: { type: Array, default: () => { return [] } },
    isReportedIdReportAdmin: { type: Boolean, required: true },
    hasReportGroup: { type: Boolean, required: true },
    hasArbitrationGroup: { type: Boolean, required: true },
    isReporterIdReportAdmin: { type: Boolean, required: true },
    isReporterIdArbitrationAdmin: { type: Boolean, required: true },
    isReportedIdArbitrationAdmin: { type: Boolean, required: true },
    isReportButtonEnabled: { type: Boolean, required: true },
    reporterHasReportGroup: { type: Boolean, required: true },
    mailboxNameReportRequest: { type: String, required: true },
  },
  data () {
    return {
      buddyType: this.initialBuddyType,
      buddyTypes: BUDDY_TYPES,
      loading: false,
    }
  },
  computed: {
    showReportButton () {
      return this.buttonNameReportRequest !== null && this.buttonNameReportRequest.length > 0
    },
    showModerationButton () {
      return this.fsId !== this.fsIdSession
    },
  },
  methods: {
    openChat (fsId) {
      conversationStore.openChatWithUser(fsId)
    },
    async sendBuddyRequest (userId) {
      const dialogueOptions = {
        title: this.$i18n('buddy.send.confirm_title', { name: this.foodSaverName }),
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
        title: this.$i18n('buddy.remove.confirm_title', { name: this.foodSaverName }),
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
    OpenHistory (type) {
      this.$refs.profileHistoryModal.showModal(this.fsId, type === 0)
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
