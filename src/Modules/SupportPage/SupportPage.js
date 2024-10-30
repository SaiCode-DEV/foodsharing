import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'
import SupportPage from './components/SupportPage.vue'

vueRegister({
  SupportPage,
})
vueApply('#support-page')
