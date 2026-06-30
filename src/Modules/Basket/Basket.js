import '@/core'
import '@/globals'

import './Basket.css'

import { vueApply, vueRegister } from '@/vue'
import BasketPage from '@/views/pages/Basket/BasketPage.vue'
import BasketFind from '@/views/pages/Basket/BasketFind.vue'
import BasketErrorPage from '@/views/pages/Basket/BasketErrorPage.vue'

vueRegister({ BasketFind, BasketPage, BasketErrorPage })

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#BasketFind')
  vueApply('#vue-basket-page')
  vueApply('#BasketErrorPage')
})
