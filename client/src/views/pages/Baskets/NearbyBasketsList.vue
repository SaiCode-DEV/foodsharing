<template>
  <Container
    :collapsible="false"
    hide-header
    class="p-3"
  >
    <h5 class="mb-3">
      {{ $t('basket.nearby-short') }}
    </h5>
    <b-list-group>
      <b-list-group-item
        v-for="basket in baskets"
        :key="basket.id"
        button
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
      </b-list-group-item>
    </b-list-group>
    <div id="go-to-map-button">
      <a class="button" :href="$url('map', { markers: 'baskets' })">{{ $t('basket.all_map') }}</a>
    </div>
    <basket-bubble ref="basketBubbleRef" />
  </Container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useUserStore } from '@/stores/user.js'
import { useBasketStore } from '@/stores/baskets'
import BasketBubble from '@php/Modules/Map/components/BasketBubble.vue'
import Container from '@/components/Container/Container.vue'

const userStore = useUserStore()
const basketStore = useBasketStore()

const baskets = ref([])
const basketBubbleRef = ref(null)

function picturePath (basket) {
  let img = '/img/basket.png'
  if (basket.picture && basket.picture.length > 0) {
    if (basket.picture.startsWith('/api')) {
      img = basket.picture + '?w=35&h=35'
    } else {
      img = '/images/basket/thumb-' + basket.picture
    }
  }
  return img
}

function openBubble (id) {
  basketBubbleRef.value.show(id)
}

function formattedDistance (basket) {
  if (basket.distanceInKm < 1) {
    return `${(Math.round(basket.distanceInKm * 100) * 10).toLocaleString()} m`
  } else {
    return `${(basket.distanceInKm).toFixed(1).toLocaleString()} km`
  }
}

onMounted(async () => {
  baskets.value = await basketStore.fetchNearby(userStore.getLocations)
})

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
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  word-wrap: break-word;

  .distance-label {
    float: right;
    margin: 0 0 0 3px;
  }
}
#go-to-map-button {
  text-align: center;
}
</style>
