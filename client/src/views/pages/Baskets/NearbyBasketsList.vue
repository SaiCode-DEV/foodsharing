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
          @click="openBubble(basket.id)"
        >
          <img
            width="35px"
            :src="picturePath(basket)"
            class="basket-picture mr-2"
            :alt="$t('terminology.basket')"
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
      <a class="button" :href="$url('map', { markers: 'baskets' })">{{ $t('basket.all_map') }}</a>
    </div>
    <basket-bubble ref="basketBubble" />
  </div>
</template>

<script>

import BasketBubble from '@php/Modules/Map/components/BasketBubble.vue'

export default {
  components: { BasketBubble },
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
    openBubble (id) {
      this.$refs.basketBubble.show(id)
    },
    formattedDistance (basket) {
      if (basket.distanceInKm < 1) {
        return `${(Math.round(basket.distanceInKm * 100) * 10).toLocaleString()} m`
      } else {
        return `${(basket.distanceInKm).toFixed(1).toLocaleString()} km`
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
