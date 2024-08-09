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
import DataUser from '@/stores/user'
// components
import ErrorField from './ErrorField.vue'
import ModalLoader from '@/views/partials/Modals/ModalLoader.vue'
import { isValidPhoneNumber } from '@/helper/phone-numbers'

export default {
  components: {
    ErrorField,
    ModalLoader, // is required because the website is not a single vue instance (each module has its own instance)  and otherwise the modals are only over datastore accessable and not by the global $bvmodal function
  },
  computed: {
    list () {
      const list = []

      DataUser.mutations.fetchDetails()

      const mobilePhoneNumber = DataUser.getters.getMobilePhoneNumber()
      if (mobilePhoneNumber && !isValidPhoneNumber(mobilePhoneNumber)) {
        list.push({
          field: 'invalid_mobile_phonenumber',
          links: [{
            text: 'error.invalid_mobile_phonenumber.link',
            urlShortHand: 'settings',
          }],
        })
      }

      const landlinePhoneNumber = DataUser.getters.getPhoneNumber()
      if (landlinePhoneNumber && !isValidPhoneNumber(landlinePhoneNumber)) {
        list.push({
          field: 'invalid_landline_phonenumber',
          links: [{
            text: 'error.invalid_landline_phonenumber.link',
            urlShortHand: 'settings',
          }],
        })
      }

      if (DataUser.getters.getAvatar() === null) {
        list.push({
          field: 'missing_user_avatar',
          links: [{
            text: 'error.missing_user_avatar.link',
            urlShortHand: 'settings',
          }],
        })
      }

      if (DataUser.getters.getAvatar() && !DataUser.getters.getAvatar().startsWith('/api/uploads/')) {
        list.push({
          field: 'old_user_avatar',
          link: 'images/' + DataUser.getters.getAvatar(),
          links: [{
            text: 'error.old_user_avatar.link',
            urlShortHand: 'settings',
          }],
        })
      }

      if (DataUser.getters.isFoodsaver() && !DataUser.getters.hasHomeRegion()) {
        this.$bvModal.show('joinRegionModal')
      }

      if (DataUser.getters.isFoodsaver() && !DataUser.getters.hasLocations()) {
        list.push({
          field: 'missing_geolocation',
          links: [{
            text: 'error.missing_geolocation.link',
            urlShortHand: 'settings',
          }],
        })
      }

      if (!DataUser.getters.hasActiveEmail()) {
        list.push({
          field: 'mail_activation',
          links: [{
            text: 'error.mail_activation.link_1',
            urlShortHand: 'resendActivationMail',
          },
          {
            text: 'error.mail_activation.link_2',
            urlShortHand: 'settings',
          }],
        })
      }

      if (DataUser.getters.hasBouncingEmail()) {
        list.push({
          field: 'mail_bounce',
          links: [{
            text: 'error.mail_bounce.link_1',
            urlShortHand: 'settings',
          },
          {
            text: 'error.mail_bounce.link_2',
            urlShortHand: 'freshdesk_locked_email',
          }],
        })
      }

      return list
    },
  },
}
</script>
