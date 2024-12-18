<template>
  <loading-overlay
    :active="busy"
    rounded="pill"
  >
    <img
      class="clickable"
      :src="`/img/wallet/google/${locale.toUpperCase()}.svg`"
      :height="50"
      :alt="$i18n('settings.passport.add_to_wallet.google')"
      @click="onClick"
    >
  </loading-overlay>
</template>

<script setup>
import { defineProps, ref } from 'vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import i18n, { locale } from '@/helper/i18n'
import { pulseError } from '@/script'
import { captureError } from '@/sentry'

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

async function onClick () {
  if (props.disabled || busy.value) return
  busy.value = true

  try {
    const response = await fetch(props.href)
    const data = await response.json()

    if (!response.ok) {
      throw captureError(data.error || i18n('settings.passport.wallet.generate_error'))
    }

    window.location.href = data.url
  } catch (error) {
    pulseError(error.message)
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.clickable {
  cursor: pointer;
}
</style>
