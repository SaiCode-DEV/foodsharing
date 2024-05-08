<template>
  <div>
    <ul class="linklist">
      <li
        v-for="basket in baskets"
        :key="basket.id"
      >
        <a
          class="ui-corner-all"
          href="#"
          @click="openBubble(basket)"
        >
          <img
            width="35px"
            :src="picturePath(basket)"
            class="basket-picture mr-2"
            :alt="$i18n('terminology.basket')"
          >
          <span class="basket-label">
            <strong class="distance-label">({{ formattedDistance(basket) }})</strong>
            {{ basket.description }}
          </span>
          <span class="clear" />
        </a>
      </li>
    </ul>
    <div id="go-to-map-button">
      <a class="button" :href="$url('map', { markers: 'baskets' })">{{ $i18n('basket.all_map') }}</a>
    </div>
  </div>
</template>

<script>

import { ajreq } from '@/script'
import { vueApply } from '@/vue'

export default {
  props: {
    baskets: { type: Array, default: () => [] },
  },
  methods: {
    picturePath (basket) {
      let img = '/img/basket.png'
      if (basket.picture && basket.picture.length > 0) {
        if (basket.picture.startsWith('/api')) {
          img = basket.picture + '?w=35&h=35'
        } else {
          img = '/images/basket/thumb-' + basket.picture
        }
      }
      return img
    },
    openBubble (basket) {
      ajreq('bubble', {
        app: 'basket',
        id: basket.id,
      }).then(_ => {
        vueApply('#basket-bubble')
      })
    },
    formattedDistance (basket) {
      const distance = Math.round(basket.distance)
      if (distance < 1) {
        return `${(distance * 1000).toLocaleString()} m`
      } else {
        return `${(distance).toLocaleString()} km`
      }
    },
  },
}
</script>

<style scoped lang="scss">
.basket-picture {
  float: left;
  border-radius: 5px
}
.basket-label {
  height: 35px;
  overflow: hidden;
  font-size: 11px;
  line-height: 16px;

  .distance-label {
    float: right;
    margin: 0 0 0 3px;
  }
}
#go-to-map-button {
  text-align: center;
}
</style>
