<!-- Leaflet map component that can display vector or tile maps.
  The slot allows adding child components like markers. -->
<template>
  <l-map
    ref="map"
    :style="mapStyle"
    :zoom="zoom"
    :center="[center.lat, center.lon]"
    :bounds="bounds"
    @ready="resetMap"
    @move="$emit('move', $event)"
    @dragend="$emit('dragend', $event)"
  >
    <!-- <l-tile-layer
      v-if="useVectorMap"
      :options="vectorLayerOptions"
      :tile-layer-class="vectorLayerClass"
      :attribution="attribution"
    /> -->
    <l-tile-layer
      :url="tileUrl"
      :attribution="attribution"
    />
    <slot />
  </l-map>
</template>

<script>
// import L from 'leaflet'
import { LMap, LTileLayer } from 'vue2-leaflet'
// import mapboxgl from 'mapbox-gl'
// import 'mapbox-gl-leaflet'
// import 'mapbox-gl/dist/mapbox-gl.css'
import 'leaflet/dist/leaflet.css'
import { MAP_ATTRIBUTION } from '@/consts'
// import { isWebGLSupported } from '@/utils'
import { getMapRasterTilesUrl } from '@/mapUtils'

// window.mapboxgl = mapboxgl // mapbox-gl-leaflet expects this to be global

export default {
  name: 'LeafletMap',
  components: { LMap, LTileLayer },
  props: {
    zoom: { type: Number, required: true },
    center: { type: Object, required: true },
    bounds: { type: Array, default: null },
    height: { type: Number, default: 300 },
  },
  data () {
    return {
      attribution: MAP_ATTRIBUTION,
      /* vectorLayerClass: (url, options) => L.mapboxGL(options),
      vectorLayerOptions: {
        accessToken: 'no-token',
        style: MAP_TILES_URL,
      }, */
      tileUrl: getMapRasterTilesUrl(),
      resizeObserver: null,
    }
  },
  computed: {
    useVectorMap () {
      // return isWebGLSupported()
      return false
    },
    mapStyle () {
      return `height: ${this.height}px`
    },
  },
  beforeDestroy () {
    if (this.resizeObserver) {
      this.resizeObserver.disconnect()
    }
  },
  methods: {
    resetMap (mapObject) {
      try {
        this.resizeObserver = new ResizeObserver((_) => {
          mapObject.invalidateSize()
        })
        this.resizeObserver.observe(this.$refs.map.$el)
      } catch (e) {
        /* ResizeObserver is not defined in some old browser versions, especially in Safari. In this case, fall back to
           a timeout. */
        try {
          setTimeout(function () { mapObject.invalidateSize() }, 1000)
        } catch (e) {
        }
      }
    },
  },
}
</script>

<style scoped>

</style>
