import '@/core'
import '@/globals'
import 'jquery-dynatree'
import { vueRegister, vueApply } from '@/vue'
import StoreRegionList from './components/StoreRegionList.vue'
import StoreUserList from './components/StoreUserList.vue'
import StoreNew from './components/StoreNew/StoreNew.vue'

const path = window.location.pathname.toLowerCase()
const regionNewStoreRegEx = /^\/region\/.*\/store\/new$/
const regionStoreListRegEx = /^\/region\/.*\/stores$/
const userStoreListRegEx = /^\/user\/.*\/stores$/

if (regionStoreListRegEx.test(path)) {
  vueRegister({
    StoreRegionList,
  })
  vueApply('#vue-store-region-list', true)
}

if (userStoreListRegEx.test(path)) {
  vueRegister({
    StoreUserList,
  })
  vueApply('#vue-store-user-list', true)
}

if (regionNewStoreRegEx.test(path)) {
  vueRegister({
    StoreNew,
  })
  vueApply('#vue-store-new', true)
}
