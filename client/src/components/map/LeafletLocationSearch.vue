<!-- Combines the LeafletLocationPicker with a text field that supports searching for addresses via geocoding. -->
<template>
  <div>
    <div class="alert alert-info">
      <i class="fas fa-info-circle" />
      {{ $t('addresspicker.infobox') }}
    </div>
    <AddressSearchField
      ref="addressSearch"
      :initial-query="initialQuery"
      @change="useAddress"
    />
    <LeafletLocationPicker
      :icon="icon"
      :coordinates="currentCoords"
      :zoom="currentZoom"
      :marker-draggable="!disabled"
      @coordinates-changed="updateCoordinates"
    />

    <div v-if="showAddressFields">
      <b-alert
        class="mt-3"
        variant="warning"
        :show="differentLocation"
      >
        <i class="fas fa-exclamation-triangle mr-2" />
        {{ $t('addresspicker.different_location_warning') }}
      </b-alert>
      <b-form-group
        :label="$t('anschrift')"
        label-for="input-street"
        class="my-2"
      >
        <b-form-input
          id="input-street"
          v-model="currentStreet"
          :disabled="disabled || !differentLocation"
          @change="emitAddressChange"
        />
      </b-form-group>
      <b-row>
        <b-col class="col-3 pr-0">
          <b-form-group
            :label="$t('plz')"
            label-for="input-postal"
            class="my-2"
          >
            <b-form-input
              id="input-postal"
              v-model="currentPostal"
              class="my-2"
              maxlength="10"
              :disabled="disabled || !differentLocation"
              @change="emitAddressChange"
            />
          </b-form-group>
        </b-col>
        <b-col>
          <b-form-group
            :label="$t('ort')"
            label-for="input-city"
            class="my-2"
          >
            <b-form-input
              id="input-city"
              v-model="currentCity"
              class="my-2"
              :disabled="disabled || !differentLocation"
              @change="emitAddressChange"
            />
          </b-form-group>
        </b-col>
      </b-row>
      <b-form-group
        v-if="allowAddressCorrection"
        class="my-2"
        cols="4"
      >
        <b-form-checkbox
          v-model="differentLocation"
          :disabled="disabled"
          switch
        >
          {{ $t('addresspicker.different_location') }}
        </b-form-checkbox>
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
import AddressSearchField from './AddressSearchField.vue'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

const props = defineProps({
  zoom: { type: Number, required: true },
  coordinates: { type: Object, required: true },
  postalCode: { type: String, default: '' },
  street: { type: String, default: '' },
  city: { type: String, default: '' },
  markerType: { type: Object, default: () => ({ icon: 'smile', color: 'orange' }) },
  showAddressFields: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  disableSnapping: { type: Boolean, default: false },
  allowAddressCorrection: { type: Boolean, default: false },
})

const emit = defineEmits(['address-change'])

const icon = L.AwesomeMarkers.icon({ icon: props.markerType.icon, markerColor: props.markerType.color })
const differentLocation = ref(false)
const currentCoords = ref(props.coordinates)
const currentPostal = ref(props.postalCode)
const currentStreet = ref(props.street)
const currentCity = ref(props.city)
const currentZoom = ref(props.zoom)
const addressSearch = ref(null)
const initialQuery = ref(getSearchPlaceholder())

function getSearchPlaceholder () {
  const search = []
  if (currentStreet.value) search.push(currentStreet.value)
  if (currentCity.value) search.push(`${currentPostal.value ?? ''} ${currentCity.value}`.trim())
  return search.join(', ')
}

function updateInputs (location) {
  const isValidAddress = location.postcode && location.city && location.street
  if (!isValidAddress) {
    currentStreet.value = location.address_line1 ?? ''
    return
  }
  currentPostal.value = location.postcode
  currentCity.value = location.city
  currentStreet.value = `${location.street} ${location.housenumber ?? ''}`.trim()
}

async function updateCoordinates (coords) {
  // if the marker was dragged, we need to do reverse geocoding to find the address
  const location = await fetchReverseGeocode(coords)
  if (!location) return
  updateInputs(location)
  addressSearch.value.setSearchString(location)
  const position = props.disableSnapping ? coords : location
  currentCoords.value = { lat: position.lat, lon: position.lon }
  emitAddressChange()
}

function useAddress (location) {
  currentCoords.value = { lat: location.lat, lon: location.lon }
  updateInputs(location)
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
