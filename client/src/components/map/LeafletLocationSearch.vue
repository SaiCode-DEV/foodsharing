<!-- Combines the LeafletLocationPicker with a text field that supports searching for addresses via geocoding. -->
<template>
  <div class="bootstrap">
    <div class="alert alert-info">
      <i class="fas fa-info-circle" />
      {{ $i18n('addresspicker.infobox') }}
      <div v-if="additionalInfoText">
        <hr>
        <Markdown :source="additionalInfoText" />
      </div>
    </div>
    <AddressSearchField ref="addressSearch" @change="useAddress" />
    <LeafletLocationPicker
      :icon="icon"
      :coordinates="currentCoords"
      :zoom="currentZoom"
      :marker-draggable="!disabled"
      @coordinates-changed="updateCoordinates"
    />

    <div v-if="showAddressFields">
      <b-form-group
        :label="$i18n('addresspicker.different_location')"
        label-for="different-location"
        class="my-2"
      >
        <b-form-checkbox
          id="different_location"
          v-model="differentLocation"
          :disabled="disabled"
          switch
        />
      </b-form-group>
      <b-form-group
        :label="$i18n('anschrift')"
        label-for="input-street"
        class="my-2"
      >
        <b-form-input
          id="input-street"
          ref="inputStreet"
          v-model="currentStreet"
          :disabled="disabled || !differentLocation"
          @change="emitAddressChange"
        />
      </b-form-group>
      <b-form-group
        :label="$i18n('plz')"
        label-for="input-postal"
        class="my-2"
      >
        <b-form-input
          id="input-postal"
          ref="inputPostal"
          v-model="currentPostal"
          class="my-2"
          :disabled="disabled || !differentLocation"
          @change="emitAddressChange"
        />
      </b-form-group>
      <b-form-group
        :label="$i18n('ort')"
        label-for="input-city"
        class="my-2"
      >
        <b-form-input
          id="input-city"
          ref="inputCity"
          v-model="currentCity"
          class="my-2"
          :disabled="disabled || !differentLocation"
          @change="emitAddressChange"
        />
      </b-form-group>
    </div>
  </div>
</template>

<script setup>
import { BFormGroup, BFormInput } from 'bootstrap-vue'
import { fetchReverseGeocode } from '@/api/geocode'
import L from 'leaflet'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import 'leaflet.awesome-markers'
import { defineProps, defineEmits, ref } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import AddressSearchField from './AddressSearchField.vue'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

const props = defineProps({
  zoom: { type: Number, required: true },
  coordinates: { type: Object, required: true },
  postalCode: { type: String, default: '' },
  street: { type: String, default: '' },
  city: { type: String, default: '' },
  iconName: { type: String, default: 'smile' },
  iconColor: { type: String, default: 'orange' },
  showAddressFields: { type: Boolean, default: true },
  additionalInfoText: { type: String, default: null },
  doReverseGeocoding: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['address-change'])

const icon = L.AwesomeMarkers.icon({ icon: props.iconName, markerColor: props.iconColor })

const differentLocation = ref(false)
const currentCoords = ref(props.coordinates)
const currentPostal = ref(props.postalCode)
const currentStreet = ref(props.street)
const currentCity = ref(props.city)
const currentZoom = ref(props.zoom)
const addressSearch = ref(null)

async function updateCoordinates (coords) {
  // if the marker was dragged, we need to do reverse geocoding to find the address
  currentCoords.value = coords
  if (props.doReverseGeocoding) {
    const location = await fetchReverseGeocode(coords)
    if (!location) return
    currentPostal.value = location.postcode
    currentCity.value = location.city
    currentStreet.value = `${location.street} ${location.housenumber}`
    addressSearch.value.setSearchString(location.formatted)
    emitAddressChange()
  }
}

function useAddress (location) {
  currentCoords.value = { lat: location.lat, lon: location.lon }
  currentPostal.value = location.postcode
  currentCity.value = location.city
  currentStreet.value = location.address_line1
  if (location.housenumber) {
    currentZoom.value = 17
  } else {
    currentZoom.value = 15
  }
  emitAddressChange()
}

function emitAddressChange () {
  emit('address-change', ...[currentCoords, currentStreet, currentPostal, currentCity].map(x => x?.value))
}
</script>
