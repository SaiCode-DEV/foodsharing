<template>
  <ul>
    <li
      v-for="region of regions"
      :key="region.id"
    >
      <Region :region="region" />
      <RegionList
        v-if="region.children"
        :regions="region.children"
      />
    </li>
  </ul>
</template>
<script>
import Region from './Region.vue'

export default {
  name: 'RegionList',
  components: { Region },
  props: {
    regions: { type: Array, required: true },
  },
}
</script>
