<template>
  <b-overlay
    id="overlay-background"
    variant="dark"
    :show="busy"
    :opacity="0.6"
    rounded="pill"
  >
    <template #overlay>
      <i class="fas fa-spinner fa-spin" />
    </template>
    <img
      class="clickable"
      :src="`/img/wallet/google/${locale.toUpperCase()}.svg`"
      :height="50"
      :alt="$i18n('settings.passport.add_to_wallet.google')"
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
    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || i18n('settings.passport.wallet.generate_error'))
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
