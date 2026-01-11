import { vueRegister, vueApply } from '@/vue'
import Notifications from '@/components/UI/Notifications.vue'

// Register under a different name to avoid colliding with the
// `vue-notification` plugin's global component named `notifications`.
vueRegister({ UiNotifications: Notifications })
// Mount notifications into the global wrapper
vueApply('#vue-ui-notifications', true)
