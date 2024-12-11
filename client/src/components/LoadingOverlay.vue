<template>
  <b-overlay
    id="overlay-background"
    variant="dark"
    :show="props.active"
    :opacity="0.6"
    :rounded="props.rounded"
  >
    <template #overlay>
      <i class="fas fa-spinner fa-spin" />
    </template>
    <slot />
  </b-overlay>
</template>

<script setup>
import { defineProps } from 'vue'

const props = defineProps({
  active: {
    type: Boolean,
    default: false,
  },
  rounded: {
    type: String,
    default: 'lg',
  },
})
</script>
