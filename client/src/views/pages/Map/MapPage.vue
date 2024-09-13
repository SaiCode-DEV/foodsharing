<template>
  <div>
    <leaflet-map
      :center.sync="currentCenter"
      :zoom.sync="currentZoom"
      height="calc(100vh - var(--navbar-height))"
    >
      <vue2-leaflet-marker-cluster
        v-for="type in selectedTypes"
        :key="type"
        :ref="`markerCluster-${type}`"
      />
      <vue2-leaflet-locatecontrol />
    </leaflet-map>
    <map-control
      :visible-types="visibleTypes"
      :selected-types="selectedTypes"
      :selected-store-types="selectedStoreTypes"
      @toggle-marker-type="toggleMarkerType"
      @select-store-marker-type="selectStoreMarkerType"
    />

    <basket-bubble ref="basketBubble" />
    <community-bubble ref="communityBubble" />
    <store-bubble ref="storeBubble" />
    <food-share-point-bubble ref="foodSharePointBubble" />
  </div>
</template>

<script>

import L from 'leaflet'
import 'leaflet.awesome-markers'
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'
import Vue2LeafletLocatecontrol from 'vue2-leaflet-locatecontrol'
import LeafletMap from '@/components/map/LeafletMap.vue'
import MapControl from '@/views/pages/Map/MapControl.vue'
import { store, MAP_CONSTANTS, MARKER_TYPES } from '@/stores/map'
import { objectMap } from '@/utils'
import { hideLoader, showLoader } from '@/script'
import BasketBubble from '@php/Modules/Map/components/BasketBubble.vue'
import CommunityBubble from '@php/Modules/Map/components/CommunityBubble.vue'
import StoreBubble from '@php/Modules/Map/components/StoreBubble.vue'
import FoodSharePointBubble from '@php/Modules/Map/components/FoodSharePointBubble.vue'
import Storage from '@/storage'
import DataUser from '@/stores/user.js'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'
const maxBasketNameLength = 30

export default {
  components: {
    MapControl,
    LeafletMap,
    Vue2LeafletMarkerCluster,
    BasketBubble,
    CommunityBubble,
    StoreBubble,
    FoodSharePointBubble,
    Vue2LeafletLocatecontrol,
  },
  props: {
    center: { type: Object, default: null },
    maySeeStores: { type: Boolean, default: false },
    selectedStoreId: { type: Number, default: null },
    selectedFoodSharePointId: { type: Number, default: null },
  },
  data () {
    return {
      currentCenter: { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON },
      currentZoom: MAP_CONSTANTS.ZOOM_COUNTRY,
      selectedTypes: [MARKER_TYPES.baskets.name],
      selectedStoreTypes: {
        status: 'cooperating',
        help: 'all',
        scope: 'region',
      },
      markers: store.state.markers,
    }
  },
  computed: {
    visibleTypes () {
      const types = [MARKER_TYPES.baskets.name, MARKER_TYPES.foodsharepoints.name, MARKER_TYPES.communities.name]
      if (this.maySeeStores) {
        types.push(MARKER_TYPES.stores.name)
      }
      return types
    },
    icons () {
      return objectMap(MARKER_TYPES, type => L.AwesomeMarkers.icon({ icon: type.icon, markerColor: type.color }))
    },
  },
  created () {
    // Restore the selected marker types from the local storage
    this.storage = new Storage('map')
    this.selectedTypes = this.storage.get('selectedTypes', this.selectedTypes)

    const saved = this.storage.get('selectedStoreTypes', this.selectedStoreTypes)
    if (!(saved instanceof Array)) { // Don't load data saved in the old format
      this.selectedStoreTypes = saved
    }

    // Remove the stores from the selected types if the user is not allowed to see them
    if (!this.maySeeStores && this.selectedTypes.includes(MARKER_TYPES.stores.name)) {
      this.selectedTypes.splice(this.selectedTypes.indexOf(MARKER_TYPES.stores.name), 1)
    }
  },
  async mounted () {
    // Figure out the initial center and zoom
    if (this.center) {
      // 1. If a specific marker is to be centered, we use that coordinates and optionally open the corresponding bubble
      if (this.selectedStoreId) {
        this.selectedTypes = [MARKER_TYPES.stores.name]
        this.$refs.storeBubble.show(this.selectedStoreId)
      } else if (this.selectedFoodSharePointId) {
        this.selectedTypes = [MARKER_TYPES.foodsharepoints.name]
      }
      this.currentCenter = this.center
      this.currentZoom = MAP_CONSTANTS.ZOOM_CITY
    } else if (DataUser.getters.hasLocations()) {
      // 2. Use the user's home location
      this.currentCenter = DataUser.getters.getLocations()
      this.currentZoom = MAP_CONSTANTS.ZOOM_CITY
    } else {
      // 3. Fall back to the default location and zoom
      this.currentCenter = { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON }
      this.currentZoom = MAP_CONSTANTS.ZOOM_COUNTRY
    }

    // Load all markers that are initially selected
    showLoader()
    await Promise.all(this.selectedTypes.map(name => store.getMarkers(name, name === MARKER_TYPES.stores.name ? this.selectedStoreTypes : {})))
    hideLoader()
    for (const type of this.selectedTypes) {
      this.drawMarkerLayer(type)
    }
  },
  methods: {
    /**
     * (De-)activates a marker type. Fetches the marker data if the type is being activated.
     */
    async toggleMarkerType (name) {
      if (this.selectedTypes.includes(name)) {
        this.selectedTypes.splice(this.selectedTypes.indexOf(name), 1)
      } else {
        this.selectedTypes.push(name)
        await store.getMarkers(name, name === MARKER_TYPES.stores.name ? this.selectedStoreTypes : {})
        this.drawMarkerLayer(name)
      }
      this.storage.set('selectedTypes', this.selectedTypes)
    },
    /**
     * (De-)activates a store marker type. Fetches the store marker data if the type is being activated.
     */
    async selectStoreMarkerType (type, value) {
      this.selectedStoreTypes[type] = value
      this.storage.set('selectedStoreTypes', this.selectedStoreTypes)
      await store.getMarkers(MARKER_TYPES.stores.name, this.selectedStoreTypes)
      this.drawMarkerLayer(MARKER_TYPES.stores.name)
    },
    /**
     * When a marker was clicked, this function toggles the corresponding action like opening a bubble.
     */
    markerClicked (type, id) {
      switch (type) {
        case MARKER_TYPES.baskets.name:
          this.$refs.basketBubble.show(id)
          break
        case MARKER_TYPES.foodsharepoints.name:
          this.$refs.foodSharePointBubble.show(id)
          break
        case MARKER_TYPES.stores.name:
          this.$refs.storeBubble.show(id)
          break
        case MARKER_TYPES.communities.name:
          this.$refs.communityBubble.show(id)
          break
      }
    },
    drawMarkerLayer (type) {
      const layer = this.$refs[`markerCluster-${type}`][0]
      if (!layer) return

      const markerList = []

      for (const markerData of this.markers[type]) {
        const marker = L.marker(L.latLng(markerData.lat, markerData.lon), { icon: this.icons[type] })
        let markerName = markerData.name

        // Baskets use their description as name, but 30 chars at max.
        // Add ellipsis in case the max length is hit
        if (type === MARKER_TYPES.baskets.name && markerName.length === maxBasketNameLength) {
          markerName += '…'
        }
        marker.bindTooltip(markerName, {
          permanent: false, // The tooltip appears on hover
          direction: 'bottom',
        })
        marker.on('click', () => this.markerClicked(type, markerData.id))

        markerList.push(marker)
      }

      layer.mapObject.clearLayers()
      layer.mapObject.addLayers(markerList)
    },
  },
}
</script>
