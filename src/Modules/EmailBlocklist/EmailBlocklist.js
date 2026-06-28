import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import EmailBlocklistAdmin from '@/views/pages/Admin/EmailBlocklist.vue'

if (window.location.pathname === '/admin/emailblocklist') {
  vueRegister({ EmailBlocklistAdmin })
  vueApply('#email-blocklist-admin')
}
