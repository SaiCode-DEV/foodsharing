<template>
  <b-modal
    ref="modal"
    centered
    :title="$i18n('notifications.pushModal.title')"
    :ok-title="$i18n('notifications.pushModal.ok')"
    :cancel-title="$i18n('notifications.pushModal.cancel')"
    :cancel-variant="dontAskAgain ? 'outline-danger' : ''"
    @ok="enablePushNotifications"
    @cancel="cancel"
  >
    <Markdown :source="$i18n('notifications.pushModal.content')" />
    <hr>
    <b-form-checkbox v-model="dontAskAgain">
      {{ $i18n('notifications.pushModal.dontAskAgain') }}
    </b-form-checkbox>
  </b-modal>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'
import PushNotificationMixin from '@/mixins/PushNotificationMixin.js'

export default {
  components: { Markdown },
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

      if (this.pushNotificationsLoading) {
        await new Promise((resolve) => { this.loadingFinishedCallback = resolve })
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
