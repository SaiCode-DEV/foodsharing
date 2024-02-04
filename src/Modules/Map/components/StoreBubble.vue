<template>
  <div>
    <div
      v-if="loading && store !== null"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div v-else>
      <div class="section">
        <label class="section-label">
          {{ $i18n('storeedit.store.status') }}
        </label>
        <div class="section-content">
          <div class="mb-2">
            <store-status-icon
              :cooperation-status="store.cooperationStatus"
            />
            <span>{{ $i18n('storestatus.' + store.cooperationStatus) }}</span>
          </div>

          <div
            v-html="$i18n('storeview.teamInfo', { active: store.teamMemberCount, jumper: store.standbyCount })"
          />

          <div
            v-if="store.pickupCount > 0"
            v-html="$i18n('storeview.pickupCount', { pickupCount: $i18n('storeview.counter', {suffix: 'x', count: store.pickupCount}) })"
          />
          <div v-if="store.pickupCount > 0">
            {{ $i18n('storeview.pickupWeight', { pickupWeight: store.pickupWeightInKg }) }}
          </div>
          <div>
            {{ $i18n('storeview.cooperation', { startTime: cooperationStartDate }) }}
          </div>
          <div v-if="pickupTimeExplanation">
            {{ $i18n('storeview.public_time', { freq: pickupTimeExplanation }) }}
          </div>
        </div>
        <div class="clear" />
      </div>

      <div
        v-if="store.managers.length > 0"
        class="section"
      >
        <label class="section-label">
          {{ $i18n('storeview.managers') }}
        </label>

        <div class="section-content">
          <a
            v-for="manager in store.managers"
            :key="manager.id"
            :href="$url('profile', manager.id)"
          >
            <Avatar
              :url="manager.avatar"
              size="50"
            />
          </a>
        </div>
        <div class="clear" />
      </div>

      <div
        v-if="store.publicInformation"
        class="section"
      >
        <label
          class="section-label ui-widget"
        >
          {{ $i18n('storeview.info') }}
        </label>
        <div class="section-content">
          {{ store.publicInformation }}
        </div>
      </div>

      <b-alert
        show
        variant="info"
      >
        {{ $i18n(`storeedit.fetch.teamStatus${store.teamSearchStatus}`) }}
      </b-alert>

      <a
        v-if="store.mayAccessStorePage"
        class="btn btn-primary action-button"
        :href="$url('store', storeId)"
      >
        {{ $i18n('store.go') }}
      </a>
      <button
        v-else-if="store.maySendRequest"
        class="btn btn-primary action-button"
        @click="sendRequest"
      >
        {{ $i18n('store.request.request') }}
      </button>
      <button
        v-else-if="store.mayWithdrawRequest"
        class="btn btn-primary action-button"
        @click="sendRequest"
      >
        {{ $i18n('store.request.withdraw') }}
      </button>
    </div>
  </div>
</template>

<script>
import { getStoreBubbleContent } from '@/api/map'
import { pulseError } from '@/script'
import StoreStatusIcon from '../../Store/components/StoreStatusIcon'
import Avatar from '@/components/Avatar'

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
      return this.store !== null
        ? this.$dateFormatter.format(this.store.cooperationStart, {
          month: 'long',
          year: 'numeric',
        })
        : ''
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
        ? translations[this.store.publicPickupTime]
        : null
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
    sendRequest () {
      // TODO
    },
  },
}
</script>

<style lang="scss" scoped>
.section {
  border-bottom: 1px solid var(--fs-border-default);
  padding-bottom: 15px;
  padding-top: 15px;
  margin-top: 0;

  .section-content {
    font-size: 14px;
    line-height: 1.38;
  }

  .section-label {
    color: var(--fs-color-primary-500);
    display: block;
    font-weight: 500;
    font-size: 12px;
    padding-top: 5px;
    line-height: 1.28;
  }
}

.action-button {
  padding: .375rem .75rem;
  display: block;
}

</style>
