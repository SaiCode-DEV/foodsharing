<template>
  <b-overlay
    id="overlay-background"
    variant="dark"
    :show="busy"
    :opacity="0.6"
    rounded="lg"
  >
    <template #overlay>
      <i class="fas fa-spinner fa-spin" />
    </template>
    <img
      class="clickable"
      :src="`/img/wallet/apple/${locale.toUpperCase()}.svg`"
      :height="50"
      :alt="$i18n('settings.passport.add_to_wallet.apple')"
      @click="onClick"
    >
  </b-overlay>
</template>
<script setup>
import { defineProps, ref } from 'vue'

import { locale } from '@/helper/i18n'

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
  href: {
    type: String,
    required: true,
    default: '#',
  },
})

const busy = ref(false)

function onClick () {
  if (props.disabled || busy.value) return
  busy.value = true
  setTimeout(() => {
    busy.value = false
  }, 8000)
  window.location.href = props.href
}

</script>
<style scoped>
.clickable {
  cursor: pointer;
}
</style>
