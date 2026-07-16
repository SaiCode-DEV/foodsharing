import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'

import DonationPage from '@/views/pages/Donation/DonationPage.vue'
import DonationAdminPage from '@/views/pages/Donation/DonationAdminPage.vue'

const path = window.location.pathname
if (path === '/donation/admin') {
  vueRegister({
    DonationAdminPage,
  })
  vueApply('#vue-donation-admin-page')
} else if (path.startsWith('/donation')) {
  vueRegister({
    DonationPage,
  })
  vueApply('#vue-donation-page')
}
