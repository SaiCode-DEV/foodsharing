<template>
  <div class="field">
    <div class="head ui-widget-header ui-corner-top">
      {{ $i18n('fsp.address') }}
    </div>
    <div class="ui-widget ui-widget-content corner-bottom margin-bottom p-2">
      <div class="input-wrapper">
        <label class="wrapper-label ui-widget" for="fsp-address">
          {{ $i18n('fsp.street') }}
        </label>
        <div id="fsp-address" class="element-wrapper">
          {{ address }}
        </div>
      </div>

      <div class="input-wrapper">
        <label class="wrapper-label ui-widget" for="fsp-city">
          {{ $i18n('fsp.location') }}
        </label>
        <div id="fsp-city" class="element-wrapper">
          {{ zipCode }} {{ city }}
        </div>

        <a :href="$url('map', { foodSharePointId: id })">
          <i class="fas fa-map-marker-alt" />
          {{ $i18n('fsp.show_on_large_map') }}
        </a>
      </div>

      <leaflet-location-picker
        :icon="icon"
        :coordinates="coordinates"
        :zoom="15"
      />
    </div>
  </div>
</template>

<script>

import L from 'leaflet'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  components: { LeafletLocationPicker },
  props: {
    id: { type: Number, required: true },
    address: { type: String, required: true },
    zipCode: { type: String, required: true },
    city: { type: String, required: true },
    coordinates: { type: Object, required: true },
  },
  data () {
    return {
      icon: L.AwesomeMarkers.icon({ icon: 'recycle', markerColor: 'beige' }),
    }
  },
}
</script>
