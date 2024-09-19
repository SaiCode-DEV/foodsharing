/* eslint-disable camelcase */
import '@/core'
import '@/globals'
import 'jquery-tagedit'
import 'jquery-tagedit-auto-grow-input'
import { vueApply, vueRegister } from '@/vue'
import { GET, goTo } from '@/browser'
import AvatarList from '@/components/Avatar/AvatarList'
import Wall from '@/components/Wall/Wall'

import './FoodSharePoint.css'

// Wallpost
import AddressField from './components/AddressField'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'
import FileUploadVForm from '@/components/upload/FileUploadVForm.vue'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import { addFoodSharePoint } from '@/api/foodsharepoints'
import i18n from '@/helper/i18n'
import $ from 'jquery'
import { expose } from '@/utils'
import { url } from '@/helper/urls'

expose({ _addFoodSharePoint })

vueRegister({
  AvatarList,
  Wall,
  AddressField,
})

const sub = GET('sub')
if (sub === 'add' || sub === 'edit') {
  vueRegister({ LeafletLocationSearchVForm, FileUploadVForm })
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

// TODO: can be removed when there is a Vue form for food share points
async function _addFoodSharePoint () {
  showLoader()
  const regionId = Number($('#fsp_bezirk_id').val())
  const name = $('#name').val()
  const description = $('#desc').val()
  const picture = $('input[name="picture"]').val()
  const address = $('input[name="anschrift"]').val()
  const postalCode = $('input[name="plz"]').val()
  const city = $('input[name="ort"]').val()
  const lat = Number($('input[name="lat"]').val())
  const lon = Number($('input[name="lon"]').val())

  try {
    const result = await addFoodSharePoint(regionId, {
      regionId: regionId,
      name: name,
      description: description,
      picture: picture,
      address: address,
      postalCode: postalCode,
      city: city,
      location: { lat: lat, lon: lon },
    })
    pulseSuccess(i18n(result.added ? 'fsp.addSuccess' : 'fsp.suggestSuccess'))
    goTo(url('foodsharepoints', regionId))
  } catch (e) {
    pulseError(i18n('fsp.addError'))
  }
  hideLoader()
}
