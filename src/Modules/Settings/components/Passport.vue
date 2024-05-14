<template>
  <div>
    <div class="head ui-widget-header">
      {{ $i18n('settings.passport.menu') }}
    </div>

    <div class="ui-widget-content corner-bottom margin-bottom ui-padding">
      <div class="bootstrap">
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
    </div>
  </div>
</template>

<script>
import { hideLoader, pulseError, showLoader } from '@/script'
import { createPassportAsUser } from '@/api/verification'
import i18n from '@/helper/i18n'
import DataUser from '@/stores/user.js'

export default {
  computed: {
    userDetails: () => DataUser.getters.getUserDetails(),
  },
  async mounted () {
    await DataUser.mutations.fetchDetails()
  },
  methods: {
    async tryCreateAsUser () {
      showLoader()
      try {
        const jsonData = await createPassportAsUser()
        const blob = new Blob(jsonData.response, { type: 'application/json' })
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
