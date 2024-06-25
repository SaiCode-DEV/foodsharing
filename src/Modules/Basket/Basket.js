/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'

import $ from 'jquery'

import './Basket.css'

import { vueApply, vueRegister } from '@/vue'
import RequestForm from '@/components/Basket/RequestForm'
import EditForm from '@/components/Basket/EditForm'
import AvatarList from '@/components/Avatar/AvatarList'
import BasketLocationMap from '@/components/Basket/BasketLocationMap'
import BasketsLocationMap from '@/components/Basket/BasketsLocationMap'
import NearbyBasketsList from '@/views/pages/Baskets/NearbyBasketsList'

$(document).ready(() => {
  // Container only exists if the current user is not the basket offerer
  const requestFormContainerId = 'vue-BasketRequestForm'
  if (document.getElementById(requestFormContainerId)) {
    vueRegister({
      RequestForm,
    })
    vueApply('#' + requestFormContainerId)
  }

  const editFormContainerId = 'vue-basket-edit-form'
  if (document.getElementById(editFormContainerId)) {
    vueRegister({
      EditForm,
    })
    vueApply('#' + editFormContainerId)
  }

  // Creator avatar is only visible on /essenskoerbe/{id}, not on /essenskoerbe/find
  if (document.getElementById('basket-creator')) {
    vueRegister({ AvatarList, BasketLocationMap })
    vueApply('#basket-creator')
    vueApply('#basket-location-map')
  } else if (document.getElementById('baskets-location-map')) {
    vueRegister({ BasketsLocationMap, NearbyBasketsList })
    vueApply('#baskets-location-map')
    vueApply('#nearby-baskets-list')
  }
})
