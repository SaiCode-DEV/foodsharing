<template>
  <div>
    <address-search-field
      :placeholder="$t('basket.mapsearch')"
      @change="updateMapCenter"
    />
    <LoadingOverlay :active="baskets.length === 0">
      <leaflet-map
        class="baskets-map"
        :zoom="currentZoom"
        :center="currentCenter"
        :bounds="currentBounds"
        height="400px"
      >
        <vue2-leaflet-marker-cluster>
          <l-marker
            v-for="basket in baskets"
            :key="basket.id"
            ref="marker"
            :lat-lng="{ lat: basket.lat, lon: basket.lon }"
            :icon="icon"
            :draggable="false"
            @click="openBasketBubble(basket.id)"
          />
        </vue2-leaflet-marker-cluster>
      </leaflet-map>
    </LoadingOverlay>
    <basket-bubble ref="basketBubbleRef" />
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useUserStore } from '@/stores/user.js'
import { MAP_CONSTANTS } from '@/stores/map'
import L from 'leaflet'
import { LMarker } from 'vue2-leaflet'
import 'leaflet.awesome-markers'
import LeafletMap from '@/components/map/LeafletMap'
import { useBasketStore } from '@/stores/baskets'
import AddressSearchField from '@/components/map/AddressSearchField'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'
import BasketBubble from '@php/Modules/Map/components/BasketBubble.vue'
import LoadingOverlay from '../LoadingOverlay.vue'
L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

const userStore = useUserStore()
const basketStore = useBasketStore()

const basketBubbleRef = ref(null)
const currentCenter = ref({
  lat: MAP_CONSTANTS.CENTER_GERMANY_LAT,
  lon: MAP_CONSTANTS.CENTER_GERMANY_LON,
})
const currentZoom = ref(MAP_CONSTANTS.ZOOM_COUNTRY)
const currentBounds = ref(null)
const baskets = ref([])

const icon = L.AwesomeMarkers.icon({ icon: 'shopping-basket', markerColor: 'green' })

onMounted(async () => {
  if (userStore.getLocations.lat !== 0 && userStore.getLocations.lon !== 0) {
    currentCenter.value = userStore.getLocations
    currentZoom.value = MAP_CONSTANTS.ZOOM_CITY
  }
  await basketStore.fetchAllCoordinates()
  baskets.value = basketStore.getAllBasketCoordinates
})

function updateMapCenter (coordinates) {
  currentCenter.value = coordinates
  currentZoom.value = 17
}

function openBasketBubble (id) {
  basketBubbleRef.value.show(id)
}
</script>

<style lang="scss">
.baskets-map {
  height: 400px;
}
</style>
