<template>
  <b-modal
    ref="modal"
    centered
    :title="$t('notifications.pushModal.title')"
    :ok-title="$t('notifications.pushModal.ok')"
    :cancel-title="$t('notifications.pushModal.cancel')"
    :cancel-variant="dontAskAgain ? 'outline-danger' : ''"
    @ok="enablePushNotifications"
    @cancel="cancel"
  >
    <Markdown :source="$t('notifications.pushModal.content')" />
    <hr>
    <b-form-checkbox v-model="dontAskAgain">
      {{ $t('notifications.pushModal.dontAskAgain') }}
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
