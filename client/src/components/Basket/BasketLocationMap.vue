<template>
  <div class="page-container page render">
    <div class="d-flex justify-content-between mb-2">
      <h3>{{ $i18n('basket.where') }}</h3>
      <NavSelector
        :latitude="coordinates.lat"
        :longitude="coordinates.lon"
      />
    </div>
    <leaflet-location-picker
      :zoom="zoom"
      :coordinates="coordinates"
      :icon="icon"
      :marker-draggable="false"
    />
  </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet.awesome-markers'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import NavSelector from '@/components/UI/Nav/NavSelector.vue'
L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  components: { LeafletLocationPicker, NavSelector },
  props: {
    zoom: { type: Number, required: true },
    coordinates: { type: Object, required: true },
  },
  data () {
    return {
      icon: L.AwesomeMarkers.icon({ icon: 'shopping-basket', markerColor: 'green' }),
    }
  },
}
</script>
