<template>
  <map-popup
    id="storeBubbleModal"
    :show-footer-close-button="showFooterCloseButton"
    :is-loading="loading"
  >
    <div v-if="store">
      <div class="card">
        <div class="card-header">
          <div class="mb-2">
            <store-status-icon :cooperation-status="store.cooperationStatus" />
            <span>{{ $t('storestatus.' + store.cooperationStatus) }}</span><span v-if="cooperationStartDate">
              ({{ cooperationStartDate }})
            </span>
          </div>

          <div>
            {{ $t('map.filters.stores.type.label') }}:
            <strong>
              {{ $t('map.filters.stores.type.' + store.categoryType) }}
            </strong>
          </div>
          <div v-if="userAndStoreHaveLocation">
            {{ $t('storeview.team_info_distance') }}
            <strong :class="distanceClass">{{ distanceDisplay }}</strong>
          </div>
          <div>
            {{ $t('terminology.region') }}:
            <a :href="$url('publicRegion', store.regionId)" target="_blank">
              <strong>
                {{ store.regionName }}
              </strong>
            </a>
          </div>
          <div>{{ $t('storeview.team_info_active') }} <strong>{{ store.teamMemberCount }}</strong></div>
          <div>{{ $t('storeview.team_info_jumper') }} <strong>{{ store.standbyCount }}</strong></div>

          <div class="mt-2">
            <span v-if="store.pickupCount > 0">
              <strong>{{ store.pickupCount }}</strong> {{ $t('storeview.pickupCount') }}
            </span>
            <br>
            <span v-if="store.pickupWeightInKg > 0">
              <strong>{{ store.pickupWeightInKg }}</strong> {{ $t('storeview.pickupWeight') }}
            </span>
          </div>

          <div v-if="pickupTimeExplanation" class="mt-2">
            {{ $t('storeview.public_time', { freq: pickupTimeExplanation }) }}
          </div>
        </div>
        <div class="card-footer text-muted" />
      </div>

      <div v-if="store.managers.length > 0" class="card mt-3">
        <div class="card-header">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">
              {{ $t('storeview.managers') }}
            </h5>
            <b-button
              v-b-tooltip="$t('store.chat.managers')"
              variant="primary"
              size="sm"
              class="ml-1"
              @click="openManagerChat"
            >
              <i class="fas fa-comments" />
            </b-button>
          </div>
        </div>
        <div class="card-body">
          <div class="d-flex flex-wrap">
            <Avatar
              v-for="manager in store.managers"
              :key="manager.id"
              class="mr-1"
              :user="manager"
              :size="50"
            />
          </div>
        </div>
      </div>

      <div v-if="store.publicInformation" class="card mt-3">
        <div class="card-header">
          <h5 class="card-title">
            {{ $t('storeview.info') }}
          </h5>
        </div>
        <div class="card-body">
          <Markdown :source="store.publicInformation" />
        </div>
      </div>

      <b-alert show variant="info">
        {{ $t(`storeedit.fetch.teamStatus${store.teamSearchStatus}`) }}
      </b-alert>

      <div class="store-alerts">
        <div v-for="alert in filteredStoreAlerts" :key="alert.id">
          <b-alert show variant="danger">
            <i :class="alert.icon" />
            {{ $t(alert.textKey, alert.textParams || {}) }}<br>
            <a
              v-if="alert.linkUrl"
              :href="alert.linkUrl"
              v-text="$t(alert.linkTextKey)"
            />
          </b-alert>
        </div>
      </div>

      <div v-if="allStoreAlerts.length > 1 && (store?.maySendRequest || store?.mayAcceptInvitation)" class="mt-1">
        <a
          href="#"
          class="more-alerts-link"
          @click.prevent="alertsExpanded = !alertsExpanded"
        >
          <span v-if="!alertsExpanded && hiddenAlertsCount === 1">{{ $t('store.request.alerts.one_more') }}</span>
          <span v-else-if="!alertsExpanded && hiddenAlertsCount > 1">{{ $t('store.request.alerts.many_more', {count: hiddenAlertsCount}) }}</span>
          <span v-else-if="hiddenAlertsCount === 1">{{ $t('store.request.alerts.one_less') }}</span>
          <span v-else>{{ $t('store.request.alerts.many_less', {count: hiddenAlertsCount}) }}</span>
        </a>
      </div>

      <b-alert :show="store.isHygieneRequired && !isMissingHygieneCertificate" variant="success">
        <i class="fas fa-hands-wash mr-2" />
        {{ $t('store.request.hygieneRequired.request') }}
      </b-alert>
      <b-alert :show="store.mayAcceptInvitation" variant="success">
        <i class="fas fa-user-check mr-2" />
        {{ $t('store.invitation.invited_info') }}
      </b-alert>
    </div>

    <template #popup-header>
      <h3 v-if="store">
        {{ store.name }}
      </h3>
    </template>
    <template #popup-footer>
      <b-collapse
        v-if="store?.maySendRequest"
        :visible="isMessageInputVisible"
        class="w-100"
      >
        <b-form-group
          class="mb-2"
          :label="$t('store.request.application-message')"
        >
          <b-form-textarea
            v-model="applicationMessage"
            :placeholder="$t('store.request.application-placeholder')"
            :state="!store.requireApplyText || applicationMessage.length >= minApplicationMessageLength"
          />
          <b-form-invalid-feedback v-if="store.requireApplyText && applicationMessage.length < minApplicationMessageLength">
            {{ $t('store.request.applicationMessageTooShort') }}
          </b-form-invalid-feedback>
        </b-form-group>
        <div class="card">
          <div>
            {{ $t('store.request.applicationSummary.intro') }}
            <ul class="mt-1">
              <li>
                {{ $t('store.request.applicationSummary.time') }}
              </li>
              <li>
                {{ $t('store.request.applicationSummary.fullName', { first_name: userStore.getUserFirstName, last_name: userStore.getUserLastName }) }}
              </li>
              <li>
                {{ $t('store.request.applicationSummary.verified', { status: userStore.isVerified ? $t('group.member_list.is_verified') : $t('group.member_list.not_verified') }) }}
              </li>
              <li>
                {{ $t('store.request.applicationSummary.distance', { distance: distanceDisplay }) }}
              </li>
              <li>
                <a
                  :href="$url('storeUserList', userStore.getUserId)"
                  target="_blank"
                  v-text="$t('store.request.applicationSummary.storeList')"
                />
              </li>
              <li v-if="store.requireApplyText">
                {{ $t('store.request.applicationSummary.text') }}
              </li>
            </ul>
          </div>
        </div>
      </b-collapse>
      <div v-if="store">
        <b-button
          v-if="store.mayAccessStorePage"
          :href="$url('store', store.id)"
          variant="success"
        >
          {{ $t('store.go') }}
        </b-button>
        <b-button
          v-if="store.mayWithdrawRequest"
          variant="success"
          @click="withdrawRequest"
        >
          {{ $t('store.request.withdraw') }}
        </b-button>
        <b-button
          v-if="store.maySendRequest"
          :variant="isMessageInputVisible ? 'success' : 'outline-secondary'"
          :disabled="isMessageInputVisible && !canSubmit"
          @click="applyToStore"
        >
          {{ $t('store.request.request') }}
        </b-button>
        <b-button
          v-if="store.isInvited"
          variant="danger"
          @click="declineInvitation"
        >
          {{ $t('store.invitation.decline') }}
        </b-button>
        <b-button
          v-if="store.isInvited"
          variant="success"
          :disabled="!store.mayAcceptInvitation"
          @click="acceptInvitation"
        >
          {{ $t('store.invitation.accept') }}
        </b-button>
      </div>
    </template>
  </map-popup>
</template>

<script>
import conversationStore from '@/stores/conversations'
import { getStoreBubbleContent } from '@/api/map'
import { pulseError, pulseSuccess } from '@/script'
import StoreStatusIcon from '../../Store/components/StoreStatusIcon'
import Avatar from '@/components/Avatar/Avatar.vue'
import { acceptInvitation, declineInvitation, declineStoreRequest, requestStoreTeamMembership } from '@/api/stores'
import { useUserStore } from '@/stores/user'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import MapBubbleMixin from './MapBubbleMixin'
import MapPopup from './MapPopup.vue'
import Markdown from '@/components/Markdown/Markdown.vue'

const maxGoodDistanceInKm = 2
const minBadDistanceInKm = 10
const minApplicationMessageLength = 25

const userStore = useUserStore()

export default {
  components: { MapPopup, Markdown, StoreStatusIcon, Avatar },
  mixins: [MapBubbleMixin],
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return {
      userStore,
      confirmationDialogue,
    }
  },
  data () {
    return {
      name: '',
      description: '',
      store: null,
      storeId: null,
      isMessageInputVisible: false,
      applicationMessage: '',
      // expose the module-level constant to the template
      minApplicationMessageLength,
      alertsExpanded: false,
    }
  },
  computed: {
    // Build an ordered list of alert objects with all rendering data
    allStoreAlerts () {
      if (!this.store) return []
      const alerts = []

      // Complete profile
      alerts.push({
        id: 'completeProfile',
        show: !this.store.hasCompleteProfile,
        icon: 'fas fa-id-card mr-2',
        textKey: this.store.isInvited ? 'store.request.completeProfileRequired.invited' : 'store.request.completeProfileRequired.request',
        linkUrl: this.$url('settings'),
        linkTextKey: 'store.request.completeProfileRequired.link',
      })

      // Home region
      alerts.push({
        id: 'homeRegion',
        show: !this.store.hasHomeRegion,
        icon: 'fas fa-location-dot mr-2',
        textKey: this.store.isInvited ? 'store.request.needsHomeRegion.invited' : 'store.request.needsHomeRegion.request',
        linkUrl: this.$url('dashboard'),
        linkTextKey: 'store.request.needsHomeRegion.link',
      })

      // Member of store region
      alerts.push({
        id: 'memberOfRegion',
        show: !this.store.isMemberOfRegion,
        icon: 'fas fa-location-pin mr-2',
        textKey: this.store.isInvited ? 'store.request.needsStoreRegion.invited' : 'store.request.needsStoreRegion.request',
        textParams: { region: this.store.regionName },
        linkUrl: this.$url('publicRegion', this.store.regionId),
        linkTextKey: 'store.request.needsStoreRegion.link',
      })

      // Verification required
      alerts.push({
        id: 'requireVerification',
        show: this.store.requireVerification,
        icon: 'fas fa-user-xmark mr-2',
        textKey: this.store.isInvited ? 'store.request.requireVerification.invited' : 'store.request.requireVerification.request',
        linkUrl: this.$url('region_forum', userStore.getHomeRegion),
        linkTextKey: 'store.request.requireVerification.link',
      })

      // Phone required
      alerts.push({
        id: 'requirePhone',
        show: this.store.requirePhone,
        icon: 'fas fa-phone-slash mr-2',
        textKey: this.store.isInvited ? 'store.request.requirePhone.invited' : 'store.request.requirePhone.request',
        linkUrl: this.$url('settings'),
        linkTextKey: 'store.request.requirePhone.link',
      })

      // Hygiene
      alerts.push({
        id: 'hygieneRequired',
        show: this.store.isHygieneRequired && this.isMissingHygieneCertificate,
        icon: 'fas fa-hands-wash mr-2',
        textKey: this.store.isInvited ? 'store.request.hygieneRequired.invited' : 'store.request.hygieneRequired.request',
        linkUrl: this.$url('settingsHygiene'),
        linkTextKey: 'store.request.hygieneRequired.link',
      })

      return alerts.filter(a => a.show)
    },
    filteredStoreAlerts () {
      return this.alertsExpanded ? this.allStoreAlerts : this.allStoreAlerts.slice(0, 1)
    },
    hiddenAlertsCount () {
      return Math.max(0, this.allStoreAlerts.length - 1)
    },
    cooperationStartDate () {
      return this.store !== null && this.store.cooperationStart
        ? this.$dateFormatter.format(this.store.cooperationStart, {
          month: 'long',
          year: 'numeric',
        })
        : null
    },
    pickupTimeExplanation () {
      if (this.store === null) {
        return null
      }
      const translations = {
        1: 'storeview.public_time_in_the_morning',
        2: 'storeview.public_time_at_noon_or_afternoon',
        3: 'storeview.public_time_in_the_evening',
        4: 'storeview.public_time_at_night',
      }

      return this.store.publicPickupTime !== null && this.store.publicPickupTime in translations
        ? this.$t(translations[this.store.publicPickupTime])
        : null
    },
    userId () {
      return this.userStore.getUserId
    },
    userLocation () {
      return this.userStore.getLocations
    },
    userAndStoreHaveLocation () {
      return this.userStore.hasLocations && this.store?.location?.lat && this.store?.location?.lon
    },
    distanceInKm () {
      if (!this.userAndStoreHaveLocation) return 0
      const toRadians = (degrees) => degrees * (Math.PI / 180)
      const R = 6371 // Earth's radius in kilometers
      const dLat = toRadians(this.store.location.lat - this.userLocation.lat)
      const dLon = toRadians(this.store.location.lon - this.userLocation.lon)
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRadians(this.userLocation.lat)) * Math.cos(toRadians(this.store.location.lat)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2)
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
      return R * c // Distance in kilometers
    },
    distanceDisplay () {
      if (this.distanceInKm < 0.95) return Math.round(this.distanceInKm * 20) * 50 + ' m'
      if (this.distanceInKm < 9.5) return Math.round(this.distanceInKm * 10) / 10 + ' km'
      return Math.round(this.distanceInKm) + ' km'
    },
    distanceClass () {
      if (this.distanceInKm < maxGoodDistanceInKm) return 'good-distance'
      if (this.distanceInKm > minBadDistanceInKm) return 'bad-distance'
      return ''
    },
    showFooterCloseButton () {
      /* The default close button in the footer is only shown if no other button is visible, so that the footer does not
         become too crowded */
      return !this.store || (!this.store.mayAccessStorePage && !this.store.maySendRequest && !this.store.mayWithdrawRequest)
    },
    isMissingHygieneCertificate () {
      return this.store.isHygieneRequired && !this.store.hasHygieneCertificate
    },
    canSubmit () {
      return !this.store.requireApplyText || this.applicationMessage.length >= minApplicationMessageLength
    },
  },
  methods: {
    async show (storeId) {
      this.isMessageInputVisible = false
      this.applicationMessage = ''
      this.storeId = storeId
      await this.timedFetchAction(
        getStoreBubbleContent(storeId),
        'storeBubbleModal',
        (data) => { this.store = data },
      )
    },
    async sendRequest () {
      try {
        let dialogueOptions = {
          title: this.$t('error.missing_geolocation.title'),
          okTitle: this.$t('store.request.confirm-no-location-ok'),
          okVariant: 'outline-danger',
        }
        if (!userStore.hasLocations) {
          if (!await this.confirmationDialogue('store.request.confirm-no-location', dialogueOptions)) return
        }
        dialogueOptions = {
          params: { distance: this.distanceDisplay },
          okTitle: this.$t('store.request.request'),
          okVariant: 'outline-danger',
        }
        if (this.distanceInKm > minBadDistanceInKm && !await this.confirmationDialogue('store.request.confirm-far', dialogueOptions)) return
        await requestStoreTeamMembership(this.store.id, this.applicationMessage || null)
        this.store.maySendRequest = false
        this.store.mayWithdrawRequest = true
        pulseSuccess(this.$t('store.request.got-it'))
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async withdrawRequest () {
      try {
        await declineStoreRequest(this.store.id, this.userId)
        this.store.maySendRequest = true
        this.store.mayWithdrawRequest = false
        pulseSuccess(this.$t('store.request.withdrawn'))
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
    },
    async applyToStore () {
      if (this.isMessageInputVisible) {
        await this.sendRequest()
      }
      this.isMessageInputVisible = !this.isMessageInputVisible
    },
    async acceptInvitation () {
      await acceptInvitation(this.storeId)
      location.href = this.$url('store', this.storeId)
    },
    async declineInvitation () {
      await declineInvitation(this.storeId)
      this.store.isInvited = false
    },
    openManagerChat () {
      const storeManagers = this.store.managers.map(item => item.id)
      conversationStore.openMultiChat(storeManagers.concat(this.userId))
    },
  },
}
</script>
<style scoped>
.good-distance {
  color: var(--fs-color-success-600)
}
.bad-distance {
  color: var(--fs-color-danger-500)
}
.more-alerts-link {
  cursor: pointer;
  color: var(--fs-color-link, #0d6efd);
  text-decoration: underline;
}
</style>
