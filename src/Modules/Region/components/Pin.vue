<template>
  <div class="bootstrap">
    <div class="card rounded">
      <div class="card-header text-white bg-primary">
        <h4 class="m-1 text-truncate">
          {{ $i18n('regionPin.header_page', { bezirk: regionName }) }}
        </h4>
      </div>
      <b-form
        :class="{disabledLoading: isLoading,'card-body': true}"
      >
        <div class="rounded p-3">
          <b-form-checkbox
            id="pin-active-check"
            v-model="isActive"
            class="ml-1 mt-2 mb-3"
          >
            {{ $i18n('regionPin.pin_visible') }}
          </b-form-checkbox>

          <LeafletLocationPicker
            :zoom="6"
            :coordinates="{lat: inlat, lon: inlon}"
            :icon="locationPickerIcon"
            :marker-draggable="true"
            @coordinates-changed="updateCoordinates"
          />

          <b-form-group
            id="input-group-lon"
            class="mt-4"
            :label="$i18n('regionPin.desc')"
            label-for="text_description"
          >
            <MarkdownInput
              :rows="12"
              :value="description"
              :placeholder="$i18n('regionPin.text_desc')"
              @update:value="newDescription => description = newDescription"
            />
          </b-form-group>

          <b-button
            class="text-right mt-2"
            @click="trySendPin"
          >
            {{ $i18n('regionPin.save') }}
          </b-button>
        </div>
      </b-form>
    </div>
  </div>
</template>
<script>
import { setRegionPin } from '@/api/regions'
import { pulseError, pulseInfo } from '@/script'
import i18n from '@/helper/i18n'
import L from 'leaflet'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import 'leaflet.awesome-markers'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

const STATUS_INACTIVE = 0
const STATUS_ACTIVE = 1

export default {
  components: { LeafletLocationPicker, MarkdownInput },
  props: {
    lat: {
      type: String,
      default: '',
    },
    lon: {
      type: String,
      default: '',
    },
    desc: {
      type: String,
      default: '',
    },
    status: { type: Number, default: STATUS_INACTIVE },
    regionId: { type: Number, required: true },
    regionName: {
      type: String,
      default: '',
    },
  },
  data () {
    return {
      isLoading: false,
      inlat: this.lat,
      inlon: this.lon,
      description: this.desc ?? '',
      isActive: this.status === STATUS_ACTIVE,
      locationPickerIcon: L.AwesomeMarkers.icon({ icon: 'users', markerColor: 'green' }),
    }
  },
  methods: {
    async trySendPin () {
      this.isLoading = true
      const status = this.isActive ? STATUS_ACTIVE : STATUS_INACTIVE
      try {
        await setRegionPin(this.regionId, this.inlat, this.inlon, this.description, status)
        pulseInfo(i18n('regionPin.success'))
      } catch (err) {
        console.error(err)
        pulseError(i18n('error_unexpected'))
      }
      this.isLoading = false
    },
    updateCoordinates (coords) {
      this.inlat = coords.lat
      this.inlon = coords.lon
    },
  },
}
</script>
