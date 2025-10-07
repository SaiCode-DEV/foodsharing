/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'

import './Basket.css'

import { vueApply, vueRegister } from '@/vue'
import BasketsLocationMap from '@/components/Basket/BasketsLocationMap'
import NearbyBasketsList from '@/views/pages/Baskets/NearbyBasketsList'
import BasketPage from '@/views/pages/Basket/BasketPage.vue'

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('baskets-location-map')) {
    vueRegister({ BasketsLocationMap, NearbyBasketsList })
    vueApply('#baskets-location-map')
    if (document.getElementById('nearby-baskets-list')) {
      vueApply('#nearby-baskets-list')
    }
  }

  if (document.getElementById('vue-basket-page')) {
    vueRegister({ BasketPage })
    vueApply('#vue-basket-page')
  }
})
