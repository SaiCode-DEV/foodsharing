import '@/core'
import '@/globals'

import './Basket.css'

import { vueApply, vueRegister } from '@/vue'
import BasketPage from '@/views/pages/Basket/BasketPage.vue'
import BasketFind from '@/views/pages/Basket/BasketFind.vue'
import BasketErrorPage from '@/views/pages/Basket/BasketErrorPage.vue'

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('BasketFind')) {
    vueRegister({ BasketFind })
    vueApply('#BasketFind')
  }

  if (document.getElementById('vue-basket-page')) {
    vueRegister({ BasketPage })
    vueApply('#vue-basket-page')
  }

  if (document.getElementById('BasketErrorPage')) {
    vueRegister({ BasketErrorPage })
    vueApply('#BasketErrorPage')
  }
})
