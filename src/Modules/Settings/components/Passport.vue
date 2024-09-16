<template>
  <div>
    <div v-if="userDetails.isVerified">
      {{ $i18n('settings.passport.verified_text') }}
    </div>
    <div v-else>
      {{ $i18n('settings.passport.non_verified_text') }}
    </div>
    <b-button
      :disabled="!userDetails.isVerified"
      class="my-2"
      @click="tryCreateAsUser()"
    >
      {{ $i18n('settings.passport.button') }}
    </b-button>
  </div>
</template>

<script>
import { hideLoader, pulseError, showLoader } from '@/script'
import { createPassportAsUser } from '@/api/verification'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user.js'

const userStore = useUserStore()

export default {
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    userDetails: () => userStore.getUserDetails,
  },
  async mounted () {
    await userStore.fetchDetails()
  },
  methods: {
    async tryCreateAsUser () {
      showLoader()
      try {
        const blob = await createPassportAsUser()
        const filename = 'fs_passport_' + this.userDetails.id + '.pdf'
        this.downloadFile(blob, filename)
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
    downloadFile (blob, filename) {
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    },
  },
}
</script>
