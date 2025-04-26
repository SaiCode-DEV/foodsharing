<template>
  <AvatarStack
    v-if="pickup.slots <= maxAvatars"
    v-b-tooltip.noninteractive="teamTooltip"
    :users="pickup.occupiedSlots.map(x => ({ avatar: x.avatar }))"
    :size="avatarSize"
    :total-slots="pickup.slots"
    :variant="variant"
  />
  <b
    v-else
    v-b-tooltip.noninteractive="teamTooltip"
    class="filled-fraction"
    v-text="`${pickup.occupiedSlots.length} / ${pickup.slots}`"
  />
</template>
<script setup>
import { computed, defineProps } from 'vue'
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import i18n from '@/helper/i18n'

const props = defineProps({
  pickup: { type: Object, required: true },
  maxAvatars: { type: Number, default: 3 },
  avatarSize: { type: Number, default: 20 },
})

const variant = computed(() => {
  if (props.pickup.isConfirmed === null) return 'info'
  if (props.pickup.isConfirmed === false) return 'danger'
  return 'success'
})

const teamTooltip = computed(() => {
  const names = props.pickup.occupiedSlots.map(x => x.name)
  if (!names.length) return ''
  const freeSlots = props.pickup.slots - props.pickup.occupiedSlots.length
  let tooltip = names.join(', ')
  if (freeSlots) {
    tooltip += ', ' + i18n('pickup.overview.freeSlots', { slots: freeSlots })
  }
  return tooltip
})
</script>
<style scoped>
.filled-fraction {
  font-size: 1.5em;
  line-height: 20px;
}
</style>
