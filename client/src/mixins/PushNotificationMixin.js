import { pulseError, pulseSuccess } from '@/script'
import { subscribeForPushNotifications, unsubscribeFromPushNotifications } from '@/pushNotifications'

export default {
  data: () => ({
    mayUsePushNotifications: false, // true if the users device has the technical requirements for push notifications
    usePushNotifications: false, // true if using, false if unable, null if not using
    pushNotificationsLoading: true, // true while async methods are running. Other data has to be reliable only if this is false.
  }),
  async mounted () {
    this.updateNotificationStatus()
  },
  methods: {
    async updateNotificationStatus () {
      this.pushNotificationsLoading = true
      this.mayUsePushNotifications = this.usePushNotifications = false
      try {
        if (
          !('serviceWorker' in navigator) ||
          !('PushManager' in window) ||
          !('Notification' in window) ||
          Notification.permission === 'denied'
        ) return
        const subscription = await (await navigator.serviceWorker.ready).pushManager.getSubscription()
        this.mayUsePushNotifications = true
        if (this.isURL(subscription?.endpoint) && Notification.permission === 'granted') {
          this.usePushNotifications = true
        }
      } finally {
        this.pushNotificationsLoading = false
      }
    },
    async updatePushNotifications (usePushNotifications) {
      if (!this.mayUsePushNotifications) return
      if (this.usePushNotifications === usePushNotifications) return
      this.pushNotificationsLoading = true
      try {
        if (!this.usePushNotifications) {
          await subscribeForPushNotifications()
          pulseSuccess(this.$i18n('settings.push.success'))
        } else {
          await unsubscribeFromPushNotifications()
          pulseSuccess(this.$i18n('settings.push.disabled'))
        }
      } catch (error) {
        pulseError(this.$i18n('error_ajax'))
        throw error
      } finally {
        await this.updateNotificationStatus()
        this.pushNotificationsLoading = false
      }
    },
    isURL (variable) {
      const urlPattern = '^(http(s):\\/\\/.)[-a-zA-Z0-9@:%._\\+~#=]{2,256}\\.[a-z]{2,6}\\b([-a-zA-Z0-9@:%_\\+.~#?&//=]*)$'
      const regex = new RegExp(urlPattern)
      return regex.test(variable)
    },
  },
}
