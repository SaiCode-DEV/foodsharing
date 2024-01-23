<template>
  <div>
    <address-search-field
      :placeholder="$i18n('basket.mapsearch')"
      @change="updateMapCenter"
    />
    <leaflet-map
      class="baskets-map"
      :zoom="currentZoom"
      :center="currentCenter"
      :bounds="currentBounds"
      :height="400"
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
  </div>
</template>

<script>
import L from 'leaflet'
import { LMarker } from 'vue2-leaflet'
import 'leaflet.awesome-markers'
import LeafletMap from '@/components/map/LeafletMap'
import BasketsData from '@/stores/baskets'
import AddressSearchField from '@/components/map/AddressSearchField'
import { openBasketBubble } from '@php/Modules/Basket/Basket'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'
L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  components: { AddressSearchField, LeafletMap, LMarker, Vue2LeafletMarkerCluster },
  props: {
    zoom: { type: Number, required: true },
    center: { type: Object, required: true },
  },
  data () {
    return {
      currentZoom: this.zoom,
      currentCenter: this.center,
      currentBounds: null,
      baskets: [],
      icon: L.AwesomeMarkers.icon({ icon: 'shopping-basket', markerColor: 'green' }),
    }
  },
  async mounted () {
    await BasketsData.mutations.fetchAllCoordinates()
    this.baskets = BasketsData.getters.getAllBasketCoordinates()
  },
  methods: {
    openBasketBubble,
    updateMapCenter (coordinates, bounds, address) {
      if (bounds) {
        this.currentBounds = bounds
      } else {
        this.currentCenter = coordinates
        this.currentZoom = 17
      }
    },
  },
}
</script>

<style lang="scss">
.baskets-map {
  height: 400px;
}
</style>
