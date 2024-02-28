<template>
  <div>
    <div v-if="loading && store !== null" class="loader-container mx-auto">
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div v-else>
      <div class="card">
        <div class="card-header">
          <div class="mb-2">
            <store-status-icon :cooperation-status="store.cooperationStatus" />
            <span>{{ $i18n('storestatus.' + store.cooperationStatus) }}</span><span v-if="cooperationStartDate">
              ({{ cooperationStartDate }})
            </span>
          </div>

          <div v-html="$i18n('storeview.teamInfo', { active: store.teamMemberCount, jumper: store.standbyCount })" />

          <div class="mt-2">
            <span v-if="store.pickupCount > 0" v-html="$i18n('storeview.pickupCount', { pickupCount: $i18n('storeview.counter', {suffix: 'x', count: store.pickupCount}) })" />
            <span v-if="store.pickupCount > 0">{{ $i18n('storeview.pickupWeight', { pickupWeight: store.pickupWeightInKg }) }}</span>
          </div>

          <div v-if="pickupTimeExplanation">
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
          {{ store.publicInformation }}
        </div>
      </div>

      <b-alert show variant="info">
        {{ $i18n(`storeedit.fetch.teamStatus${store.teamSearchStatus}`) }}
      </b-alert>

      <div class="text-center">
        <a
          v-if="store.mayAccessStorePage"
          href="#"
          class="btn btn-primary mt-3 text-wrap"
        >{{ $i18n('store.go') }}</a>
        <button
          v-else-if="store.maySendRequest"
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
    </div>
  </div>
</template>

<script>
import { getStoreBubbleContent } from '@/api/map'
import { pulseError, pulseSuccess } from '@/script'
import StoreStatusIcon from '../../Store/components/StoreStatusIcon'
import Avatar from '@/components/Avatar/Avatar.vue'
import { declineStoreRequest, requestStoreTeamMembership } from '@/api/stores'
import UserData from '@/stores/user'

export default {
  components: { StoreStatusIcon, Avatar },
  props: {
    storeId: { type: Number, required: true },
  },
  data () {
    return {
      loading: true,
      name: '',
      description: '',
      store: [],
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
      return UserData.getters.getUserId()
    },
  },
  async mounted () {
    this.loading = true
    try {
      this.store = await getStoreBubbleContent(this.storeId)
    } catch (e) {
      pulseError(this.$i18n('error_unexpected'))
    }
    this.loading = false
  },
  methods: {
    async sendRequest () {
      try {
        await requestStoreTeamMembership(this.store.id, this.userId)
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
