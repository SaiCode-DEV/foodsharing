<template>
  <div>
    <GenderList :region-id="regionId" />
    <AgeBandList :region-id="regionId" />
    <PickupList />
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
  },
}
</script>
