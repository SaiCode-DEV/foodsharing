import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import OAuthAuthorize from '@/views/pages/OAuth/OAuthAuthorize.vue'
import OAuthClientsAdmin from '@/views/pages/Admin/OAuthClients.vue'

switch (window.location.pathname) {
  case '/oauth/authorize':
    vueRegister({ OAuthAuthorize })
    vueApply('#oauth-authorize')
    break
  case '/admin/oauthclients':
    vueRegister({ OAuthClientsAdmin })
    vueApply('#oauth-clients-admin')
    break
}
