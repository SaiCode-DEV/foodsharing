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
    <b-button
      pill
      variant="outline-secondary"
      :disabled="props.disabled || busy"
      class="h-100 px-4"
      @click="tryCreateAsUser()"
    >
      <i class="fas fa-file-pdf fa-xl mr-1" />
      {{ $t('settings.passport.button') }}
    </b-button>
  </b-overlay>
</template>
<script setup>
import { defineProps, ref } from 'vue'
import { pulseError } from '@/script'
import { createPassportAsUser } from '@/api/verification'
import { useUserStore } from '@/stores/user.js'
import i18n from '@/helper/i18n'

const userStore = useUserStore()

const props = defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
})

const busy = ref(false)

async function tryCreateAsUser () {
  if (props.disabled || busy.value) return
  busy.value = true
  try {
    const blob = await createPassportAsUser()
    const filename = 'fs_passport_' + userStore.getUserId + '.pdf'
    downloadFile(blob, filename)
  } catch (e) {
    console.error(e)
    pulseError(i18n('error_unexpected'))
  } finally {
    busy.value = false
  }
}

function downloadFile (blob, filename) {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

</script>
<style scoped>
.clickable {
  cursor: pointer;
}
</style>
