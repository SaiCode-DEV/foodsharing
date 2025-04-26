<template>
  <div class="list-group-item px-2 py-1">
    <h6
      class="font-weight-bold"
      :class="{ 'is-soon': isSoon(options[0]) }"
      v-text="$dateFormatter.date(options[0].date, {type: 'full'})"
    />
    <a
      v-for="option in options"
      :key="option.date + '-' + option.store.id"
      :href="$url('store', option.store.id)"
      class="d-flex align-items-center pickup-entry"
      :class="{ 'muted': variant(option) !== 'info' }"
    >
      <h6 :class="{ 'is-soon': isSoon(option) }" v-text="$dateFormatter.time(option.date)" />
      <i
        v-if="option.description"
        v-b-tooltip="option.description"
        class="fas fa-info-circle"
      />
      <span class="field-container field-subline mr-1" v-text="option.store.name" />
      <b-badge pill :variant="variant(option)">
        <PickupTeam :pickup="option" />
      </b-badge>
    </a>
  </div>
</template>
<script setup>
import dateFormatter from '@/helper/date-formatter'
import PickupTeam from './PickupTeam.vue'
import { defineProps } from 'vue'

defineProps({
  options: { type: Array, required: true },
})

const variant = (option) => {
  if (option.isConfirmed === null) return 'info'
  if (option.isConfirmed === false) return 'danger'
  return 'success'
}

const isSoon = (option) => {
  return dateFormatter.getDifferenceToNowInHours(new Date(option.date)) < 4
}

</script>
<style lang="css" scoped>
.muted {
  opacity: 0.5;
}
.is-soon {
  color: var(--fs-color-danger-500);
}
.pickup-entry {
  gap: 0.25em;
  font-weight: normal;
  color: inherit;
}
</style>
