<template>
  <BasePage>
    <Container
      :collapsible="false"
      hide-header
      class="p-3"
    >
      <h1>
        {{ $t('terminology.baskets') }}
      </h1>
      <BasketsLocationMap ref="map" />
    </Container>

    <template v-if="userStore.isLoggedIn" #right>
      <NearbyBasketsList @select-basket="goToBasket" />
    </template>
  </BasePage>
</template>

<script setup>
import { useUserStore } from '@/stores/user'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import BasketsLocationMap from '@/components/Basket/BasketsLocationMap.vue'
import Container from '@/components/Container/Container.vue'
import NearbyBasketsList from '../Baskets/NearbyBasketsList.vue'
import { ref } from 'vue'

const userStore = useUserStore()
const map = ref(null)

function goToBasket (basket) {
  map.value.moveToBasket(basket.id)
}
</script>
