import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'

// View: Index
import Index from '@/views/pages/Index/Index.vue'

vueRegister({
  Index,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#index')
})
