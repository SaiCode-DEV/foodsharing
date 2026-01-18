import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import { GET } from '@/browser'
import AvatarList from '@/components/Avatar/AvatarList'
import Wall from '@/components/Wall/Wall'

import './FoodSharePoint.css'

// Wallpost
import FoodSharePointAddOrEdit from './components/FoodSharePointAddOrEdit.vue'
import FoodSharePoint from '@/views/pages/FoodSharePoint/FoodSharePoint.vue'

vueRegister({
  AvatarList,
  Wall,
})

const sub = GET('sub')
if (sub === 'add' || sub === 'edit') {
  vueRegister({ FoodSharePointAddOrEdit })
  vueApply('#food-share-point-add-or-edit')
} else if (sub === 'ft') {
  vueApply('#vue-wall')

  // The lists of followers and managers are only included if they are not empty
  if (document.getElementById('fsp-followers')) {
    vueApply('#fsp-followers')
  }
  if (document.getElementById('fsp-managers')) {
    vueApply('#fsp-managers')
  }
  vueApply('#fsp-address-field')
}
if (/^\/fairteiler\/\d+$/.test(location.pathname)) {
  vueRegister({ FoodSharePoint })
  vueApply('#food-share-point')
}
if (
  /^\/fairteiler\/\d+\/edit$/.test(location.pathname) ||
  /^\/fairteiler\/add(?:\/\d+)?$/.test(location.pathname)
) {
  vueRegister({ FoodSharePointAddOrEdit })
  vueApply('#food-share-point-add-or-edit')
}
