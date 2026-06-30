import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import StoreRegionList from './components/StoreRegionList.vue'
import StoreUserList from './components/StoreUserList.vue'
import StoreNew from './components/StoreNew/StoreNew.vue'

vueRegister({
  StoreRegionList,
  StoreUserList,
  StoreNew,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-store-region-list')
  vueApply('#vue-store-user-list')
  vueApply('#vue-store-new')
})
