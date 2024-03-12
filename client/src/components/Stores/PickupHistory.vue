<template>
  <Container
    :title="$i18n('pickup.history.title')"
    :container-is-expanded="isContainerExpanded"
    tag="pickup_history"
  >
    <div class="corner-bottom margin-bottom bootstrap pickup-history">
      <DateRangePicker ref="dateRange" :cooperation-start="cooperationStart" />
      <div class="p-1 pickup-search-button">
        <b-button
          variant="secondary"
          size="sm"
          class="d-block mx-auto"
          :class="{'disabled': isLoading}"
          @click.prevent="searchHistory"
        >
          <i class="fas fa-fw fa-search" />
          {{ $i18n('pickup.history.search') }}
        </b-button>
      </div>

      <div class="p-1 pickup-table">
        <Pickup
          v-for="pickupDate in pickupList"
          :key="`${pickupDate[0].storeId}-${pickupDate[0].date_ts}`"
          v-bind="pickupDate"
          :date="pickupDate[0].date"
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
import DateRangePicker from './DateRangePicker.vue'

export default {
  components: { Pickup, Container, DateRangePicker },
  props: {
    collapsedAtFirst: { type: Boolean, default: true },
    storeId: { type: Number, default: null },
    cooperationStart: { type: String, default: null },
  },
  data () {
    return {
      isContainerExpanded: false,
      isLoading: false,
      pickupList: [],
    }
  },
  methods: {
    async searchHistory () {
      if (this.isLoading || this.storeId === null) {
        return
      }
      this.isLoading = true

      try {
        const [startDate, toDate] = this.$refs.dateRange.getDateRange()
        this.pickupList = await listPickupHistory(this.storeId, startDate, toDate)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected') + e)
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
