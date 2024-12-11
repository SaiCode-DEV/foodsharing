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
import i18n, { locale } from '@/helper/i18n'
import { pulseError } from '@/script'

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

    // Check if we got a JSON response (error case)
    const contentType = response.headers.get('content-type')
    if (contentType && contentType.includes('application/json')) {
      const data = await response.json()
      if (!response.ok) {
        throw new Error(data.error || i18n('settings.passport.wallet.generate_error'))
      }
    }

    // Handle binary file download
    if (response.ok) {
      const blob = await response.blob()
      // Create a temporary download link
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'pass.pkpass'
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
      return
    }

    throw new Error('Failed to download pass')
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
