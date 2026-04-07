<!-- Extension of the LeafletMap that contains a single marker for marking or choosing a location. The marker can new made draggable.
  In this case, the chosen coordinates are emitted in a "coordinates-changed" event when dropping the marker. -->
<template>
  <LeafletMap
    ref="leafletMap"
    :zoom="zoom"
    :center="safeCenter"
    :bounds="bounds"
  >
    <LMarker
      ref="marker"
      :lat-lng="safeCenter"
      :icon="icon"
      :draggable="markerDraggable"
      @dragend="onMarkerDragEnd"
    />
  </LeafletMap>
</template>

<script>
import LeafletMap from './LeafletMap'
import { LMarker } from 'vue2-leaflet'
import { getCoordinateOrSafeDefaultForMap } from '@/mapUtils'

export default {
  name: 'LeafletLocationPicker',
  components: { LeafletMap, LMarker },
  props: {
    zoom: { type: Number, required: true },
    coordinates: { type: Object, required: true },
    bounds: { type: Array, default: null },
    icon: { type: Object, required: true },
    markerDraggable: { type: Boolean, default: false },
  },
  computed: {
    safeCenter () {
      return getCoordinateOrSafeDefaultForMap(this.coordinates)
    },
  },
  methods: {
    /**
     * Sets the map's boundaries to the rectangular spanned by the two coordinates.
     */
    onMarkerDragEnd (event) {
      const coords = event.target.getLatLng()
      this.$emit('coordinates-changed', { lat: coords.lat, lon: coords.lng })
    },
  },
}
</script>
