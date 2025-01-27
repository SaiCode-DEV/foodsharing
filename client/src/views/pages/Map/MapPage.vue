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
      :selected-specifiers="selectedSpecifiers"
      :ambassador-regions="ambassadorRegions"
      @toggle-marker-type="toggleMarkerType"
      @update-marker-specifier="updateMarkerSpecifier"
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
import { getMarkers, MAP_CONSTANTS, MARKER_TYPES } from '@/stores/map'
import { objectMap } from '@/utils'
import { hideLoader, showLoader } from '@/script'
import BasketBubble from '@php/Modules/Map/components/BasketBubble.vue'
import CommunityBubble from '@php/Modules/Map/components/CommunityBubble.vue'
import StoreBubble from '@php/Modules/Map/components/StoreBubble.vue'
import FoodSharePointBubble from '@php/Modules/Map/components/FoodSharePointBubble.vue'
import Storage from '@/storage'
import { useUserStore } from '@/stores/user.js'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'
const maxBasketNameLength = 30
const userStore = useUserStore()

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
    initialZoom: { type: Number, default: null },
    maySeeStores: { type: Boolean, default: false },
    selectedStoreId: { type: Number, default: null },
    selectedFoodSharePointId: { type: Number, default: null },
    ambassadorRegions: { type: Array, default: () => [] },
    loadMarkers: { type: String, default: null }, // Comma-separated list of additionally loaded marker types
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      currentCenter: { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON },
      currentZoom: MAP_CONSTANTS.ZOOM_COUNTRY,
      selectedTypes: [MARKER_TYPES.baskets.name],
      selectedSpecifiers: {
        stores: {
          status: 'cooperating',
          help: 'all',
          scope: 'region',
        },
        users: {
          region: this.ambassadorRegions?.[0]?.id,
          activity: 'month',
          role: 'foodsaver',
          member: 'homeregion',
        },
      },
    }
  },
  computed: {
    visibleTypes () {
      const types = [MARKER_TYPES.baskets.name, MARKER_TYPES.foodsharepoints.name, MARKER_TYPES.communities.name]
      if (this.maySeeStores) {
        types.push(MARKER_TYPES.stores.name)
      }
      if (this.ambassadorRegions?.length) {
        types.push(MARKER_TYPES.users.name)
      }
      return types
    },
    icons () {
      return objectMap(MARKER_TYPES, type => L.AwesomeMarkers.icon({ icon: type.icon, markerColor: type.color }))
    },
    maySeeUsers () {
      return Boolean(this.ambassadorRegions?.length)
    },
  },
  created () {
    // Restore the selected marker types from the local storage
    this.storage = new Storage('map')
    this.selectedTypes = this.storage.get('selectedTypes', this.selectedTypes)

    // Additionally load marker types given in loadMarkers prop
    if (this.loadMarkers) {
      const missingTypes = this.loadMarkers.split(',').filter(type =>
        Object.keys(MARKER_TYPES).includes(type) && !this.selectedTypes.includes(type),
      )
      this.selectedTypes.push(...missingTypes)
    }

    const saved = this.storage.get('selectedSpecifiers', this.selectedSpecifiers)
    if (!(saved instanceof Array)) { // Don't load data saved in the old format
      this.selectedSpecifiers = saved
    }

    // Remove unallowed selections from selected types
    if (!this.maySeeStores && this.selectedTypes.includes(MARKER_TYPES.stores.name)) {
      this.selectedTypes.splice(this.selectedTypes.indexOf(MARKER_TYPES.stores.name), 1)
    }
    if (!this.maySeeUsers && this.selectedTypes.includes(MARKER_TYPES.users.name)) {
      this.selectedTypes.splice(this.selectedTypes.indexOf(MARKER_TYPES.users.name), 1)
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
    } else if (userStore.hasLocations) {
      // 2. Use the user's home location
      this.currentCenter = userStore.getLocations
      this.currentZoom = MAP_CONSTANTS.ZOOM_CITY
    } else {
      // 3. Fall back to the default location and zoom
      this.currentCenter = { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON }
      this.currentZoom = MAP_CONSTANTS.ZOOM_COUNTRY
    }
    if (this.initialZoom && this.initialZoom >= 1) {
      // 4. allow zoom override
      this.currentZoom = this.initialZoom
    }

    // Load and draw all markers that are initially selected
    showLoader()
    await Promise.all(this.selectedTypes.map(name => this.drawMarkerLayer(name)))
    hideLoader()
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
        await this.drawMarkerLayer(name)
      }
      this.storage.set('selectedTypes', this.selectedTypes)
    },
    /**
     * (De-)activates a store marker type. Fetches the store marker data if the type is being activated.
     */
    async updateMarkerSpecifier (markerType, specifier, newValue) {
      this.selectedSpecifiers[markerType][specifier] = newValue
      this.storage.set('selectedSpecifiers', this.selectedSpecifiers)
      this.drawMarkerLayer(markerType)
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
        case MARKER_TYPES.users.name:
          location.href = this.$url('profile', id)
          break
      }
    },
    async drawMarkerLayer (type) {
      const markersData = await getMarkers(type, this.selectedSpecifiers[type])
      const layer = this.$refs[`markerCluster-${type}`][0]
      if (!layer) return

      const markerList = []

      for (const markerData of markersData) {
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
