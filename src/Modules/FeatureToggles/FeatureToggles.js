import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'

import FeatureToggles from '@/views/pages/FeatureToggles/FeatureToggles.vue'

vueRegister({
  FeatureToggles,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-feature-toggles')
})
