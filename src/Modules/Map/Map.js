import '@/core'
import '@/globals'

import { vueApply, vueRegister } from '@/vue'
import MapPage from '@/views/pages/Map/MapPage.vue'

vueRegister({
  MapPage,
})
vueApply('#map-page')
