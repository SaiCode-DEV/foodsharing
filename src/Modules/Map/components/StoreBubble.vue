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
            <span>{{ $i18n('storestatus.' + store.cooperationStatus) }}</span><span v-if="cooperationStartDate">
              ({{ cooperationStartDate }})
            </span>
          </div>

          <div v-if="userAndStoreHaveLocation">
            {{ $i18n('storeview.team_info_distance') }}
            <strong :class="distanceClass">{{ distanceDisplay }}</strong>
          </div>
          <div>{{ $i18n('storeview.team_info_active') }} <strong>{{ store.teamMemberCount }}</strong></div>
          <div>{{ $i18n('storeview.team_info_jumper') }} <strong>{{ store.standbyCount }}</strong></div>

          <div class="mt-2">
            <span v-if="store.pickupCount > 0">
              <strong>{{ store.pickupCount }}</strong> {{ $i18n('storeview.pickupCount') }}
            </span>
            <br>
            <span v-if="store.pickupWeightInKg > 0">
              <strong>{{ store.pickupWeightInKg }}</strong> {{ $i18n('storeview.pickupWeight') }}
            </span>
          </div>

          <div v-if="pickupTimeExplanation" class="mt-2">
            {{ $i18n('storeview.public_time', { freq: pickupTimeExplanation }) }}
          </div>
        </div>
        <div class="card-footer text-muted" />
      </div>

      <div v-if="store.managers.length > 0" class="card mt-3">
        <div class="card-header">
          <h5 class="card-title">
            {{ $i18n('storeview.managers') }}
          </h5>
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
            {{ $i18n('storeview.info') }}
          </h5>
        </div>
        <div class="card-body">
          <Markdown :source="store.publicInformation" />
        </div>
      </div>

      <b-alert show variant="info">
        {{ $i18n(`storeedit.fetch.teamStatus${store.teamSearchStatus}`) }}
      </b-alert>
    </div>

    <template #popup-header>
      <h3 v-if="store">
        {{ store.name }}
      </h3>
    </template>
    <template #popup-footer>
      <div v-if="store">
        <a
          v-if="store.mayAccessStorePage"
          :href="$url('store', store.id)"
          class="btn btn-primary mt-3 text-wrap"
        >{{ $i18n('store.go') }}</a>
        <button
          v-if="store.maySendRequest"
          class="btn btn-primary mt-3 text-wrap"
          @click="sendRequest"
        >
          {{ $i18n('store.request.request') }}
        </button>
        <button
          v-else-if="store.mayWithdrawRequest"
          class="btn btn-primary mt-3 text-wrap"
          @click="withdrawRequest"
        >
          {{ $i18n('store.request.withdraw') }}
        </button>
      </div>
    </template>
  </map-popup>
</template>

<script>
import { getStoreBubbleContent } from '@/api/map'
import { pulseError, pulseSuccess } from '@/script'
import StoreStatusIcon from '../../Store/components/StoreStatusIcon'
import Avatar from '@/components/Avatar/Avatar.vue'
import { declineStoreRequest, requestStoreTeamMembership } from '@/api/stores'
import { useUserStore } from '@/stores/user'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import MapBubbleMixin from './MapBubbleMixin'
import MapPopup from './MapPopup.vue'
import Markdown from '@/components/Markdown/Markdown.vue'

const maxGoodDistanceInKm = 2
const minBadDistanceInKm = 10

const userStore = useUserStore()

export default {
  components: { MapPopup, Markdown, StoreStatusIcon, Avatar },
  mixins: [ConfirmationDialogue, MapBubbleMixin],
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      name: '',
      description: '',
      store: null,
      storeId: null,
    }
  },
  computed: {
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
        ? this.$i18n(translations[this.store.publicPickupTime])
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
  },
  methods: {
    async show (storeId) {
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
          title: this.$i18n('error.missing_geolocation.title'),
          okTitle: this.$i18n('store.request.confirm-no-location-ok'),
          okVariant: 'outline-danger',
        }
        if (!userStore.hasLocations) {
          if (!await this.confirmationDialogue('store.request.confirm-no-location', dialogueOptions)) return
        }
        dialogueOptions = {
          params: { distance: this.distanceDisplay },
          okTitle: this.$i18n('store.request.request'),
          okVariant: 'outline-danger',
        }
        if (this.distanceInKm > minBadDistanceInKm && !await this.confirmationDialogue('store.request.confirm-far', dialogueOptions)) return
        await requestStoreTeamMembership(this.store.id)
        this.store.maySendRequest = false
        this.store.mayWithdrawRequest = true
        pulseSuccess(this.$i18n('store.request.got-it'))
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    async withdrawRequest () {
      try {
        await declineStoreRequest(this.store.id, this.userId)
        this.store.maySendRequest = true
        this.store.mayWithdrawRequest = false
        pulseSuccess(this.$i18n('store.request.withdrawn'))
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
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
</style>
