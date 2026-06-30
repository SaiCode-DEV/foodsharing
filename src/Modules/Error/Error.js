import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'

import Error from '@/views/pages/Error.vue'

vueRegister({
  Error,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#errorbox')
})
