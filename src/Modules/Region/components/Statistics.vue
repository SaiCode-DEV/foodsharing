<template>
  <div>
    <GenderList :region-id="regionId" />
    <AgeBandList :region-id="regionId" />
    <PickupList
      :pickup-data-daily-tab="pickupData.daily"
      :pickup-data-weekly-tab="pickupData.weekly"
      :pickup-data-monthly-tab="pickupData.monthly"
      :pickup-data-yearly-tab="pickupData.yearly"
    />
  </div>
</template>

<script>
import GenderList from './GenderList.vue'
import AgeBandList from './AgeBandList.vue'
import PickupList from './PickupList.vue'

export default {
  name: 'Statistics',
  components: { PickupList, AgeBandList, GenderList },
  props: {
    regionId: { type: Number, required: true },
    pickupData: { type: Object, required: true },
  },
}
</script>
