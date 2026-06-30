import '@/core'
import '@/globals'
import LegalPage from './components/LegalPage.vue'
import { vueApply, vueRegister } from '@/vue'

vueRegister({
  LegalPage,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#legal-page')
})
