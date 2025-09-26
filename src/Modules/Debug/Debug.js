import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'

// View: Debug
import Debug from '@/views/pages/Debug/Debug.vue'

vueRegister({
  Debug,
})
vueApply('#debug')
