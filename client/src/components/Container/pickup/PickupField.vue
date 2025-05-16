<template>
  <a
    class="pickup-field list-group-item list-group-item-action field field--stack"
    :class="{
      'muted': muteSignUps && entry.isConfirmed !== null,
    }"
    :href="$url('store', entry.store.id)"
  >
    <div class="d-flex justify-content-between align-items-center">
      <h6 class="field-headline field-headline--big" :class="{ 'text-danger': isSoon }">
        {{ $dateFormatter.date(date, {type: 'full'}) }}
      </h6>
      <h6 v-if="isSoon" class="field-headline field-headline--big text-danger">
        {{ $dateFormatter.time(date) }}
      </h6>
      <h6 v-else class="field-headline field-headline--big">
        {{ $dateFormatter.time(date) }}
      </h6>
    </div>
    <span class="field-container m-0">
      <span class="d-flex flex-column field-subline mr-1">
        <small
          v-b-tooltip.noninteractive="entry.store.name.length > 30 ? entry.store.name : ''"
          class="field-subline mr-0"
          v-text="entry.store.name"
        />
        <small v-if="entry.description" class="field-subline mr-0">
          <i v-b-tooltip="entry.description" class="fas fa-info-circle" />
          <i v-text="entry.description" />
        </small>
      </span>
      <b-badge
        pill
        class="d-flex align-items-center"
        :variant="variant"
      >
        <i
          v-b-tooltip.noninteractive="iconTooltip"
          class="fas mr-1 status-icon"
          :class="{
            'fa-question-circle': entry.isConfirmed === null,
            'fa-clock': entry.isConfirmed === false,
            'fa-check-circle': entry.isConfirmed === true,
          }"
        />
        <PickupTeam
          :pickup="entry"
          :max-avatars="4"
          :avatar-size="25"
        />
      </b-badge>
    </span>
  </a>
</template>
<script setup>
import { computed, defineProps } from 'vue'
import PickupTeam from './PickupTeam.vue'
import i18n from '@/helper/i18n'
import dateFormatter from '@/helper/date-formatter'

const props = defineProps({
  entry: { type: Object, default: () => ({}) },
  muteSignUps: { type: Boolean, default: false },
})

const date = computed(() => new Date(props.entry.date))

const isSoon = computed(() => {
  return dateFormatter.getDifferenceToNowInHours(date.value) < 4
})

const iconTooltip = computed(() => {
  if (props.entry.isConfirmed === null) return i18n('pickup.may_enter')
  if (props.entry.isConfirmed === false) return i18n('pickup.to_be_confirmed')
  return i18n('pickup.confirmed')
})

const variant = computed(() => {
  if (props.entry.isConfirmed === null) return 'info'
  if (props.entry.isConfirmed === false) return 'danger'
  return 'success'
})
</script>
<style lang="scss" scoped>
.pickup-field.muted > * {
  opacity: 0.5;
}
.pickup-field .status-icon {
  font-size: 1.5em;
}
</style>
