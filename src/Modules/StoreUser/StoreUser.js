import StorePage from '@/views/pages/Store/StorePage.vue'
import { vueApply, vueRegister } from '@/vue'

import '@/core'
import '@/globals'
import './StoreUser.css'

vueRegister({
  StorePage,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-store-page')
})
