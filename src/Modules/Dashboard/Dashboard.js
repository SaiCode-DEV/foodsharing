import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'

// View: Dashboard
import '@/views/pages/Dashboard/Dashboard.scss'
import Dashboard from '@/views/pages/Dashboard/Dashboard.vue'

vueRegister({
  Dashboard,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#dashboard')
})
