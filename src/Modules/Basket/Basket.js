/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'

import $ from 'jquery'

import { ajax } from '@/script'
import './Basket.css'

import { vueApply, vueRegister } from '@/vue'
import RequestForm from '@/components/Basket/RequestForm'
import EditForm from '@/components/Basket/EditForm'
import { listBasketCoordinates } from '@/api/baskets'
import AvatarList from '@/components/AvatarList'
import BasketLocationMap from '@/components/Basket/BasketLocationMap'
import BasketsLocationMap from '@/components/Basket/BasketsLocationMap'
import { URL_PART } from '@/browser'
import BasketBubble from '../Map/components/BasketBubble'

const mapsearch = {
  lat: null,
  lon: null,
  $basketList: null,

  appendList: function (basket) {
    let img = '/img/basket.png'

    if (basket.picture != '') {
      if (basket.picture.startsWith('/api')) {
        return `${basket.picture}`
      }
      img = `/images/basket/thumb-${basket.picture}`
    }

    let distance = Math.round(basket.distance)

    if (distance == 1) {
      distance = '1 km'
    } else if (distance < 1) {
      distance = `${distance * 1000} m`
    } else {
      distance = `${distance} km`
    }

    this.$basketList.append(`<li><a class="ui-corner-all" onclick="ajreq('bubble',{app:'basket',id:${basket.id},modal:1});return false;" href="#"><span style="float:left;margin-right:7px;"><img width="35px" src="${img}" class="ui-corner-all"></span><span style="height:35px;overflow:hidden;font-size:11px;line-height:16px;"><strong style="float:right;margin:0 0 0 3px;">(${distance})</strong>${basket.description}</span><span class="clear"></span></a></li>`)
  },
}

if ($('#mapsearch').length > 0) {
  listBasketCoordinates().then((data) => {
    if (data.length > 0) {
      mapsearch.setMarker(data)
    }
  }).catch()

  $('#map-latLng').on('change', function () {
    // console.log()

    ajax.req('basket', 'nearbyBaskets', {
      data: {
        coordinates: JSON.parse($('#map-latLng').val()),
      },
      success: function (ret) {
        if (ret.baskets != undefined) {
          mapsearch.fillBasketList(ret.baskets)
        }
      },
    })
  })
}

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
    vueRegister({ BasketsLocationMap })
    vueApply('#baskets-location-map')
  } else if (URL_PART(1) === 'find') {
    vueRegister({ BasketBubble })
  }
})
