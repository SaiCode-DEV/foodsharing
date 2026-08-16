<template>
  <div class="map-legend-selection">
    <b-form-group
      v-for="[selectType, options] in Object.entries(MARKER_SELECT_TYPES.stores)"
      :key="selectType"
      label-cols="4"
      class="map-legend-font-size"
      :label="$t(`map.filters.stores.${selectType}.label`) + ':'"
      :label-for="`${selectType}-select`"
    >
      <b-select
        :id="`${selectType}-select`"
        :value="props.selectedSpecifiers[selectType]"
        class="w-100 map-legend-font-size"
        size="sm"
        :options="options.map(x => ({ text: $t(`map.filters.stores.${selectType}.${x}`), value: x }))"
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
})

</script>
<style lang="scss" scoped>
.map-legend-font-size {
  font-size: 0.7rem;
}

.map-legend-selection {
  padding: 0.5rem 0.25rem 0 0.25rem;

  ::v-deep .form-row {
    margin-bottom: 0.25rem;

    &:last-child {
      margin-bottom: 0;
    }
  }
}
</style>
