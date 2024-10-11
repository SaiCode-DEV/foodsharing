<template>
  <div>
    <GenderList :region-name="regionName" :region-id="regionId" />
    <AgeBandList :region-name="regionName" :region-id="regionId" />
    <PickupList :region-name="regionName" :region-id="regionId" />
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
    regionName: { type: String, required: true },
  },
}
</script>
