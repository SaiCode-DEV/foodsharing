<template>
  <Container
    :title="$t('pickup.history.title')"
    :container-is-expanded="isContainerExpanded"
    :tag="`store-pickup-history-${storeId}`"
    wrap-content
  >
    <div class="corner-bottom margin-bottom bootstrap pickup-history">
      <DateRangePicker
        :from-date.sync="fromDate"
        :to-date.sync="toDate"
        :min-from-date="new Date(Date.parse(cooperationStart))"
        :max-to-date="new Date()"
        class="py-2"
        short
      />
      <div class="p-1 pickup-search-button">
        <b-button
          variant="secondary"
          size="sm"
          class="d-block mx-auto"
          :class="{'disabled': isLoading}"
          @click.prevent="searchHistory"
        >
          <i class="fas fa-fw fa-search" />
          {{ $t('pickup.history.search') }}
        </b-button>
      </div>

      <div class="p-1 pickup-table">
        <Pickup
          v-for="pickupDate in pickupList"
          :key="`${pickupDate[0].storeId}-${pickupDate[0].date_ts}`"
          v-bind="pickupDate"
          :date="pickupDate[0].date"
          :sign-up-date="pickupDate[0].signUpDate"
          :store-id="pickupDate[0].storeId"
          :store-title="pickupDate[0].storeTitle"
          :occupied-slots="pickupDate"
          class="pickup-block"
        />
      </div>
    </div>
  </Container>
</template>

<script>
import { listPickupHistory } from '@/api/pickups'
import { pulseError } from '@/script'
import Pickup from '@/components/Stores/Pickup/Pickup.vue'
import Container from '@/components/Container/Container.vue'
import DateRangePicker from '@/components/DateTime/DateRangePicker.vue'

export default {
  components: { Pickup, Container, DateRangePicker },
  props: {
    collapsedAtFirst: { type: Boolean, default: true },
    storeId: { type: Number, default: null },
    cooperationStart: { type: String, default: null },
  },
  data () {
    const now = new Date()
    const lastWeek = new Date(now)
    lastWeek.setDate(now.getDate() - 7)

    return {
      isContainerExpanded: false,
      isLoading: false,
      pickupList: [],
      fromDate: lastWeek,
      toDate: now,
    }
  },
  methods: {
    async searchHistory () {
      if (this.isLoading || this.storeId === null) {
        return
      }
      this.isLoading = true

      try {
        const endOfToDate = new Date(this.toDate)
        endOfToDate.setDate(this.toDate.getDate() + 1)
        this.pickupList = await listPickupHistory(this.storeId, this.fromDate, endOfToDate)
      } catch (e) {
        pulseError(this.$t('error_unexpected') + e)
      }
      this.isLoading = false
    },
  },
}
</script>

<style lang="scss" scoped>
.pickup-history::v-deep .pickup .pickup-text {
  margin-left: 0;
  margin-right: 0;
}
</style>
