import '@/core'
import '@/globals'
import 'jquery-dynatree'
import { vueRegister, vueApply } from '@/vue'
import StoreRegionList from './components/StoreRegionList.vue'
import StoreOwnList from './components/StoreOwnList.vue'
import StoreNew from './components/StoreNew/StoreNew.vue'

import { GET } from '@/script'

if (GET('a') === undefined) {
  vueRegister({
    StoreRegionList,
  })
  vueApply('#vue-store-region-list', true)
}

if (GET('a') === 'own') {
  vueRegister({
    StoreOwnList,
  })
  vueApply('#vue-store-own-list', true)
}

if (GET('a') === 'new') {
  vueRegister({
    StoreNew,
  })
  vueApply('#vue-store-new', true)
}
