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

    <b-form-group>
      <b-form-input
        id="search-address-input"
        v-model="searchInput"
        list="suggestions"
        debounce="300"
        :placeholder="$i18n('addresspicker.placeholder')"
        :disabled="disabled"
      />
      <!-- TODO: Datalist is Buggy! Use (Vuetify) Autocomplete! -->
      <datalist id="suggestions">
        <option
          v-for="(suggestion, index) in suggestions"
          :key="index"
        >
          {{ suggestion.formatted }}
        </option>
      </datalist>
    </b-form-group>
    <LeafletLocationPicker
      ref="locationPicker"
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
import { fetchAutocomplete, fetchReverseGeocode } from '@/api/geocode'
import L from 'leaflet'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import 'leaflet.awesome-markers'
import { defineProps, defineEmits, ref, watch } from 'vue'
import 'corejs-typeahead'
import Markdown from '@/components/Markdown/Markdown.vue'

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

const autocompleteLoading = ref(false)
const suggestions = ref([])
const searchInput = ref('')

const differentLocation = ref(false)
const currentCoords = ref(props.coordinates)
const currentPostal = ref(props.postalCode)
const currentStreet = ref(props.street)
const currentCity = ref(props.city)
const currentZoom = ref(props.zoom)

watch(() => searchInput.value, fetchSuggestions)

async function fetchSuggestions (input) {
  suggestions.value = []
  if (!autocompleteLoading.value) {
    autocompleteLoading.value = true
    fetchAutocomplete(input)
      .then((data) => {
        if (data?.length === 0) {
          autocompleteLoading.value = false
          return
        }
        suggestions.value = data
        updateMap(suggestions.value[0])
      })
      .finally(() => {
        autocompleteLoading.value = false
      })
  }
}

async function updateCoordinates (coords) {
  // if the marker was dragged, we need to do reverse geocoding to find the address
  currentCoords.value = coords
  if (props.doReverseGeocoding) {
    fetchReverseGeocode(coords)
      .then((data) => {
        if (!data) {
          return
        }
        currentPostal.value = data.postcode
        currentCity.value = data.city
        currentStreet.value = data.street + ' ' + data.housenumber
      })
  }
}
/**
 * This function is called when a suggestion was selected in the search field.
 */
function updateMap (searchResult) {
  // update the address data
  if (!searchResult) {
    return
  }
  currentCoords.value = { lat: searchResult.lat, lon: searchResult.lon }
  currentPostal.value = searchResult.postcode
  currentCity.value = searchResult.city
  currentStreet.value = searchResult.address_line1
  if (searchResult.housenumber) {
    currentZoom.value = 17
  } else {
    currentZoom.value = 15
  }
  emitAddressChange()
}
function emitAddressChange () {
  emit('address-change', currentCoords, currentStreet, currentPostal, currentCity)
}
</script>

<style scoped>

</style>
