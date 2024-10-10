<template>
  <div class="map-legend-selection">
    <b-form-group
      v-for="[selectType, options] in Object.entries(MARKER_SELECT_TYPES.stores)"
      :key="selectType"
      label-cols="4"
      :label="$i18n(`map.filters.stores.${selectType}.label`) + ':'"
      :label-for="`${selectType}-select`"
    >
      <b-select
        :id="`${selectType}-select`"
        :value="props.selectedSpecifiers[selectType]"
        class="w-100"
        size="sm"
        :options="options.map(x => ({ text: $i18n(`map.filters.stores.${selectType}.${x}`), value: x }))"
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
