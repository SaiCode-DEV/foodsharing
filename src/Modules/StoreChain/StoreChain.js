import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import ChainList from '@/views/pages/ChainList/ChainList.vue'

import {
  GET,
} from '@/script'

vueRegister({
  ChainList,
})
document.addEventListener('DOMContentLoaded', () => {
  if (GET('a') === undefined) {
    vueApply('#vue-chainlist')
  }
})
