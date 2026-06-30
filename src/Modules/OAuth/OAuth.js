import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import OAuthAuthorize from '@/views/pages/OAuth/OAuthAuthorize.vue'
import OAuthClientsAdmin from '@/views/pages/Admin/OAuthClients.vue'

vueRegister({ OAuthAuthorize, OAuthClientsAdmin })

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#oauth-authorize')
  vueApply('#oauth-clients-admin')
})
