<template>
  <div class="map-legend-selection">
    <b-form-group
      label-cols="4"
      class="map-legend-font-size"
      :label="$t(`map.filters.users.region.label`) + ':'"
      label-for="region-select"
    >
      <b-select
        id="region-select"
        :value="props.selectedSpecifiers.region"
        class="w-100 map-legend-font-size"
        size="sm"
        :options="regions.map(region => ({ text: region.name, value: region.id }))"
        @change="newValue => $emit('update-specifier', 'region', newValue)"
      />
    </b-form-group>
    <b-form-group
      v-for="[selectType, options] in Object.entries(MARKER_SELECT_TYPES.users)"
      :key="selectType"
      class="map-legend-font-size"
      label-cols="4"
      :label="$t(`map.filters.users.${selectType}.label`) + ':'"
      :label-for="`${selectType}-select`"
    >
      <b-select
        :id="`${selectType}-select`"
        :value="props.selectedSpecifiers[selectType]"
        class="w-100 map-legend-font-size"
        size="sm"
        :options="options.map(x => ({ text: $t(`map.filters.users.${selectType}.${x}`), value: x }))"
        @change="newValue => $emit('update-specifier', selectType, newValue)"
      />
    </b-form-group>
  </div>
</template>
<script setup>
import { MARKER_SELECT_TYPES } from '@/stores/map'
import { defineProps } from 'vue'

const props = defineProps({
  selectedSpecifiers: { type: Object, required: true },
  regions: { type: Array, required: true },
})

</script>
