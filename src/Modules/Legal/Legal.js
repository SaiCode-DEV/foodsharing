import '@/core'
import '@/globals'
import LegalPage from './components/LegalPage.vue'
import { vueApply, vueRegister } from '@/vue'

vueRegister({
  LegalPage,
})
vueApply('#legal-page')
