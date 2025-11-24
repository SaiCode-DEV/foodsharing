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
      <BasketsLocationMap />
    </Container>

    <template v-if="userStore.isLoggedIn" #right>
      <NearbyBasketsList />
    </template>
  </BasePage>
</template>

<script setup>
import { useUserStore } from '@/stores/user.js'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import BasketsLocationMap from '@/components/Basket/BasketsLocationMap.vue'
import Container from '@/components/Container/Container.vue'
import NearbyBasketsList from '../Baskets/NearbyBasketsList.vue'

const userStore = useUserStore()
</script>
