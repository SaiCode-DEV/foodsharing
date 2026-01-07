<template>
  <div class="error__container">
    <ErrorField
      v-for="(entry, key) in list"
      :key="key"
      :entry="entry"
    />
    <ModalLoader />
  </div>
</template>

<script>
// Stores
import { useUserStore } from '@/stores/user'
// components
import ErrorField from './ErrorField.vue'
import ModalLoader from '@/views/partials/Modals/ModalLoader.vue'
import { isValidPhoneNumber } from '@/helper/phone-numbers'

export default {
  components: {
    ErrorField,
    ModalLoader, // is required because the website is not a single vue instance (each module has its own instance)  and otherwise the modals are only over datastore accessable and not by the global $bvmodal function
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    list () {
      const list = []

      this.userStore.fetchDetails()

      const mobilePhoneNumber = this.userStore.getMobilePhoneNumber
      if (mobilePhoneNumber && !isValidPhoneNumber(mobilePhoneNumber)) {
        list.push({
          field: 'invalid_mobile_phonenumber',
          links: [{
            text: 'error.invalid_mobile_phonenumber.link',
            urlShorthand: 'settings',
          }],
        })
      }

      const landlinePhoneNumber = this.userStore.getPhoneNumber
      if (landlinePhoneNumber && !isValidPhoneNumber(landlinePhoneNumber)) {
        list.push({
          field: 'invalid_landline_phonenumber',
          links: [{
            text: 'error.invalid_landline_phonenumber.link',
            urlShorthand: 'settings',
          }],
        })
      }

      if (this.userStore.getAvatar === null) {
        list.push({
          field: 'missing_user_avatar',
          links: [{
            text: 'error.missing_user_avatar.link',
            urlShorthand: 'settings',
          }],
        })
      }

      if (this.userStore.isFoodsaver && !this.userStore.hasHomeRegion) {
        this.$bvModal.show('joinRegionModal')
      }

      if (this.userStore.isFoodsaver && !this.userStore.hasLocations) {
        list.push({
          field: 'missing_geolocation',
          links: [{
            text: 'error.missing_geolocation.link',
            urlShorthand: 'settings',
          }],
        })
      }

      // TODO: this can be removed as soon as login without activation is not possible anymore
      if (!this.userStore.hasActiveEmail) {
        list.push({
          field: 'mail_activation',
          links: [{
            text: 'error.mail_activation.link_1',
            urlShorthand: 'resendActivationMail',
          },
          {
            text: 'error.mail_activation.link_2',
            urlShorthand: 'settingsChangeEmail',
          }],
        })
      }

      if (this.userStore.hasBouncingEmail) {
        list.push({
          field: 'mail_bounce',
          links: [{
            text: 'error.mail_bounce.link_1',
            urlShorthand: 'settings',
          },
          {
            text: 'error.mail_bounce.link_2',
            urlShorthand: 'helpdesk_locked_email',
          }],
        })
      }
      if (this.userStore.isFoodsaver && this.userStore.hadPassport && this.userStore.isPassportInvalid) {
        list.push({
          field: 'passport_is_invalid',
          links: [{
            text: 'error.passport_is_invalid.link',
            urlShorthand: 'settingsPassport',
          },
          ],
        })
      } else if (this.userStore.isFoodsaver && this.userStore.hadPassport && this.userStore.isPassportInvalidSoon) {
        list.push({
          field: 'passport_is_invalid_soon',
          days: this.userStore.details.lastPassUntilValidInDays,
          severity: 'warning',
          links: [{
            text: 'error.passport_is_invalid_soon.link',
            urlShorthand: 'settingsPassport',
          },
          ],
        })
      }

      return list
    },
  },
}
</script>
