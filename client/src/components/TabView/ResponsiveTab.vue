<script setup>
import { defineProps } from 'vue'
defineProps({
  title: {
    type: String,
    required: true,
  },
  active: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <div>
    <slot />
  </div>
</template>
