<template>
  <b-nav-item
    :href="computedHref"
    :to="computedTo"
    @click="modal ? $bvModal.show(modal) : null"
  >
    <slot name="icon">
      <i
        v-if="icon"
        class="icon-nav fas"
        :class="icon"
      />
    </slot>
    <slot name="text">
      <span class="nav-text" v-text="title" />
      <span class="sr-only" v-text="title" />
    </slot>
  </b-nav-item>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  href: {
    type: String,
    default: null,
  },
  to: {
    type: [String, Object],
    default: null,
  },
  modal: {
    type: String,
    default: undefined,
  },
  icon: {
    type: String,
    default: undefined,
  },
})

const url = computed(() => props.to || props.href)

const isExternal = computed(() => {
  if (!url.value || typeof url.value === 'object') return false
  return /^(https?|mailto|tel):/i.test(String(url.value))
})

const computedHref = computed(() => {
  if (!url.value) return null
  return isExternal.value ? url.value : null
})

const computedTo = computed(() => {
  if (!url.value) return null
  return isExternal.value ? null : url.value
})
</script>
