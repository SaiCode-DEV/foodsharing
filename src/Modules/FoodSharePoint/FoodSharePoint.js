/* eslint-disable camelcase */
import '@/core'
import '@/globals'
import 'jquery-tagedit'
import 'jquery-tagedit-auto-grow-input'
import 'jquery-jcrop'
import { vueApply, vueRegister } from '@/vue'
import { GET } from '@/browser'
import AvatarList from '@/components/AvatarList'
import Wall from '@/components/Wall/Wall'

import './FoodSharePoint.css'

// Wallpost
import AddressField from './components/AddressField'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'

vueRegister({
  AvatarList,
  Wall,
  AddressField,
})

const sub = GET('sub')
if (sub === 'add' || sub === 'edit') {
  vueRegister({ LeafletLocationSearchVForm })
  vueApply('#foodsharepoint-address-search')
  vueApply('#image-upload')
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
