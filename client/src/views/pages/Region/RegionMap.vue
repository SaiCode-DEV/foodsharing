<template>
  <Container
    :title="$i18n('map.title')"
    tag="publicRegionMap"
  >
    <div class="list-group-item p-0">
      <LeafletMap
        :zoom="11"
        :center="center"
        height="400px"
      >
        <LMarker
          v-if="location"
          :lat-lng="location"
          :icon="icons.communities"
        />
        <LMarker
          v-for="fsp in foodSharePoints"
          :key="fsp.id"
          :name="fsp.name"
          :lat-lng="fsp"
          :icon="icons.foodsharepoints"
          @click="showFoodSharePoint(fsp.id)"
        >
          <LTooltip :options="{ direction: 'bottom' }">
            {{ fsp.name }}
          </LTooltip>
        </LMarker>
      </LeafletMap>
    </div>
    <FoodSharePointBubble ref="foodSharePointBubble" />
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import LeafletMap from '@/components/map/LeafletMap.vue'
import { LMarker, LTooltip } from 'vue2-leaflet'
import FoodSharePointBubble from '@php/Modules/Map/components/FoodSharePointBubble.vue'
import Leaflet from 'leaflet'
import { MARKER_TYPES } from '@/stores/map'
import { objectMap } from '@/utils'
Leaflet.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  components: { Container, LeafletMap, LMarker, LTooltip, FoodSharePointBubble },
  props: {
    location: { type: Object, default: null },
    foodSharePoints: { type: Array, default: () => [] },
  },
  computed: {
    icons () {
      return objectMap(MARKER_TYPES, type => Leaflet.AwesomeMarkers.icon({ icon: type.icon, markerColor: type.color }))
    },
    center () {
      function median (values) {
        values.sort((a, b) => a - b)
        const mid = Math.floor(values.length / 2)
        return values.length % 2 !== 0 ? values[mid] : (values[mid - 1] + values[mid]) / 2
      }

      function medianLatLon (coords) {
        const lats = coords.map(c => c.lat)
        const lons = coords.map(c => c.lon)
        return { lat: median(lats), lon: median(lons) }
      }

      return this.location ?? medianLatLon(this.foodSharePoints)
    },
  },
  methods: {
    showFoodSharePoint (id) {
      this.$refs.foodSharePointBubble.show(id)
    },
  },
}
</script>
