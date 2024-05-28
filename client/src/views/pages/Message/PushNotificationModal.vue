<template>
  <b-modal
    ref="modal"
    centered
    title="Push-Benachrichtigungen erhalten?"
    ok-title="Push-Benachrichtigungen erhalten"
    cancel-title="nicht aktivieren"
    :cancel-variant="dontAskAgain ? 'outline-danger' : ''"
    @ok="enablePushNotifications"
    @cancel="cancel"
  >
    Damit du schneller mitbekommst, wenn andere Foodsaver:innen dir Nachrichten schicken, kannst du auf diesem Gerät Push-Benachrichtigungen aktivieren!
    <hr>
    In deinen <a href="#">Benachichtigungseinstellungen</a> kannst du diese Benachrichtigungen jeder Zeit wieder abschalten.
    <hr>
    <b-form-checkbox v-model="dontAskAgain">
      Nicht wieder nachfragen
    </b-form-checkbox>
  </b-modal>
</template>
<script>
import PushNotificationMixin from '@/mixins/PushNotificationMixin.js'

export default {
  mixins: [PushNotificationMixin],
  data: () => ({
    dontAskAgain: false,
    bufferTime: 1000 * 60 * 60 * 24 * 10, // after dismissing the modal, ask again after 10 days
  }),
  methods: {
    async maybeShow () {
      const askForPushNotifications = JSON.parse(localStorage.getItem('askForPushNotifications'))
      if (askForPushNotifications === false) return
      if (askForPushNotifications + this.bufferTime > Date.now()) return

      while (this.pushNotificationsLoading) {
        await new Promise(resolve => window.setTimeout(resolve, 100))
      }
      if (this.mayUsePushNotifications && this.usePushNotifications) return
      this.$refs.modal.show()
      localStorage.setItem('askForPushNotifications', Date.now())
    },
    enablePushNotifications () {
      this.updatePushNotifications(true)
    },
    cancel () {
      if (this.dontAskAgain) {
        localStorage.setItem('askForPushNotifications', false)
      }
    },
  },
}
</script>
