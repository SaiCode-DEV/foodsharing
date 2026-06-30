import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import AvatarList from '@/components/Avatar/AvatarList'
import Wall from '@/components/Wall/Wall'

import './FoodSharePoint.css'

// Wallpost
import FoodSharePointAddOrEdit from './components/FoodSharePointAddOrEdit.vue'
import FoodSharePoint from '@/views/pages/FoodSharePoint/FoodSharePoint.vue'

vueRegister({
  AvatarList,
  Wall,
  FoodSharePointAddOrEdit,
  FoodSharePoint,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#food-share-point-add-or-edit')
  vueApply('#vue-wall')
  vueApply('#fsp-followers')
  vueApply('#fsp-managers')
  vueApply('#fsp-address-field')
  vueApply('#food-share-point')
})
