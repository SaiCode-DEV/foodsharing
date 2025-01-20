<template>
  <Container
    v-if="center || mayEdit"
    :title="$i18n('map.title')"
    tag="publicRegionMap"
  >
    <template v-if="!editMode" #options>
      <OverflowMenu icon="cog" :options="options" />
    </template>
    <template v-if="!editMode">
      <div v-if="center" class="list-group-item p-0">
        <LeafletMap
          ref="map"
          :zoom.sync="zoom"
          :center.sync="center"
          height="400px"
          style="max-height: 60vh;"
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
      <div v-else class="list-group-item">
        <b-alert
          show
          class="m-0"
        >
          <i class="fas fa-edit mr-2" />
          {{ $i18n('region.public.map.missing') }}
        </b-alert>
      </div>
      <ContainerButton
        v-if="center"
        variant="success"
        text-key="region.public.show_on_large_map"
        icon="fas fa-map-marker-alt"
        :href="largeMapLink"
      />
    </template>
    <template v-else>
      <LeafletLocationPicker
        :zoom="6"
        :coordinates="editLocation"
        :icon="icons.communities"
        :marker-draggable="true"
        @coordinates-changed="newLocation => Object.assign(editLocation, newLocation)"
      />
      <ContainerButton
        variant="danger"
        text-key="button.cancel"
        icon="fas fa-times"
        :disabled="loading"
        @click="cancel"
      />
      <ContainerButton
        variant="success"
        text-key="button.save"
        icon="fas fa-save"
        :disabled="loading"
        @click="save"
      />
    </template>
    <FoodSharePointBubble ref="foodSharePointBubble" />
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import LeafletMap from '@/components/map/LeafletMap.vue'
import { LMarker, LTooltip } from 'vue2-leaflet'
import FoodSharePointBubble from '@php/Modules/Map/components/FoodSharePointBubble.vue'
import Leaflet from 'leaflet'
import { MAP_CONSTANTS, MARKER_TYPES } from '@/stores/map'
import { objectMap } from '@/utils'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker.vue'
import { setRegionPin } from '@/api/regions'
import { useRegionStore } from '@/stores/regions'
import { pulseError } from '@/script'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
Leaflet.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

const regionStore = useRegionStore()

function median (values) {
  values.sort((a, b) => a - b)
  const mid = Math.floor(values.length / 2)
  return values.length % 2 !== 0 ? values[mid] : (values[mid - 1] + values[mid]) / 2
}

function medianLatLon (coords) {
  if (!coords.length) return null
  const lats = coords.map(c => c.lat)
  const lons = coords.map(c => c.lon)
  return { lat: median(lats), lon: median(lons) }
}

export default {
  components: { Container, LeafletMap, LMarker, LTooltip, FoodSharePointBubble, ContainerButton, OverflowMenu, LeafletLocationPicker },
  mixins: [ConfirmationDialogue],
  props: {
    regionId: { type: Number, required: true },
    location: { type: Object, default: null },
    foodSharePoints: { type: Array, default: () => [] },
    mayEdit: { type: Boolean, default: false },
  },
  data () {
    return {
      editMode: false,
      loading: false,
      editLocation: null,
      zoom: 11,
      center: this.location ?? medianLatLon(this.foodSharePoints),
    }
  },
  computed: {
    icons () {
      return objectMap(MARKER_TYPES, type => Leaflet.AwesomeMarkers.icon({ icon: type.icon, markerColor: type.color }))
    },
    options () {
      if (!this.mayEdit) return []
      if (this.location) {
        return [
          { icon: 'pen', textKey: 'region.public.map.edit_pin', callback: this.startEdit },
          { icon: 'trash', textKey: 'region.public.map.hide_pin', callback: this.remove },
        ]
      }
      return [
        { icon: 'pen', textKey: 'region.public.map.set_pin', callback: this.startEdit },
      ]
    },
    isLocationChanged () {
      return !this.location || this.location.lat !== this.editLocation.lat || this.location.lon !== this.editLocation.lon
    },
    largeMapLink () {
      return this.$url('map', { center: this.center, zoom: this.zoom, markers: ['communities', 'foodsharepoints'] })
    },
  },
  methods: {
    showFoodSharePoint (id) {
      this.$refs.foodSharePointBubble.show(id)
    },
    startEdit () {
      this.editLocation = this.location ? Object.assign({}, this.location) : { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON }
      this.editMode = true
    },

    async cancel () {
      if (this.isLocationChanged) {
        if (!await this.confirmationDialogue('region.public.map.confirm_discard_changes', {
          okTitle: this.$i18n('region.public.discard_changes'),
          cancelTitle: this.$i18n('region.public.continue_editing'),
        })) return
      }
      this.editMode = false
    },
    async save () {
      if (this.isLocationChanged) {
        if (!await this.confirmationDialogue('region.public.map.confirm_save_changes', {
          okTitle: this.$i18n('button.save'),
          okVariant: 'success',
        })) return
        this.loading = true
        try {
          await setRegionPin(this.regionId, Object.assign({ status: 1 }, this.editLocation))
          await regionStore.fetchPublicRegionData(this.regionId, true)
          this.$emit('update:location', this.editLocation)
          this.center = Object.assign({}, this.editLocation)
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
      this.editMode = false
      this.loading = false
    },
    async remove () {
      if (!await this.confirmationDialogue('region.public.map.confirm_remove', {
        okTitle: this.$i18n('button.delete'),
      })) return
      this.loading = true
      try {
        await setRegionPin(this.regionId, { status: 0 })
        await regionStore.fetchPublicRegionData(this.regionId, true)
        this.$emit('update:location', null)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      this.editMode = false
      this.loading = false
    },
  },
}
</script>
