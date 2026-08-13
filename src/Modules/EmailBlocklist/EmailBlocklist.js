import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import EmailBlocklistAdmin from '@/views/pages/Admin/EmailBlocklist.vue'

vueRegister({ EmailBlocklistAdmin })

if (window.location.pathname === '/admin/emailblocklist') {
  vueApply('#email-blocklist-admin')
}
