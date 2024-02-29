<!-- Combines the LeafletLocationPicker with a text field that supports searching for addresses via geocoding. -->
<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
  <div class="bootstrap">
    <div class="alert alert-info">
      <i class="fas fa-info-circle" />
      {{ $i18n('addresspicker.infobox') }}
      <div v-if="additionalInfoText">
        <hr>
        <span v-html="additionalInfoText" />
      </div>
    </div>
    <b-form-group>
      <b-form-input
        id="searchinput"
        v-model="searchInput"
        :placeholder="$i18n('addresspicker.placeholder')"
        :disabled="disabled"
      />
    </b-form-group>
    <LeafletLocationPicker
      ref="locationPicker"
      :icon="icon"
      :coordinates="currentCoords"
      :zoom="currentZoom"
      :bounds="currentBounds"
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
          ref="differentLocation"
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

<script>
import { BFormGroup, BFormInput } from 'bootstrap-vue'
import { locale } from '@/helper/i18n'
import { isTest } from '@/helper/server-data'

import L from 'leaflet'
import LeafletLocationPicker from '@/components/map/LeafletLocationPicker'
import 'leaflet.awesome-markers'

import $ from 'jquery'
import 'corejs-typeahead'
import 'typeahead-address-photon'

L.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

export default {
  name: 'LeafletLocationSearch',
  components: { BFormGroup, BFormInput, LeafletLocationPicker },
  props: {
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
  },
  data () {
    return {
      icon: L.AwesomeMarkers.icon({ icon: this.iconName, markerColor: this.iconColor }),
      differentLocation: null,
      currentCoords: this.coordinates,
      currentPostal: this.postalCode,
      currentStreet: this.street,
      currentCity: this.city,
      searchInput: null,
      geolocationSearchEngine: null,
      currentZoom: this.zoom,
      currentBounds: null,
    }
  },
  mounted () {
    // create the geocoding search engine
    this.geolocationSearchEngine = new window.PhotonAddressEngine({
      url: isTest ? '/mock/photon' : 'https://photon.komoot.io',
      formatResult: function (feature) {
        const prop = feature.properties
        return [prop.name || '', prop.street, prop.housenumber || '', prop.postcode, prop.city, prop.country].filter(Boolean).join(' ')
      },
      lang: locale,
    })

    // bind the search engine to the text field
    const searchpanel = $('#searchinput')
    searchpanel.typeahead({
      highlight: true,
      minLength: 3,
      hint: true,
    }, {
      displayKey: 'description',
      source: this.geolocationSearchEngine.ttAdapter(),
    })
    this.geolocationSearchEngine.bindDefaultTypeaheadEvent(searchpanel)

    // update the map when an address suggestion was selected
    $(this.geolocationSearchEngine).on('addresspicker:selected', this.updateMap)
  },
  methods: {
    updateCoordinates (coords) {
      this.currentCoords = coords
      // if the marker was dragged, we need to do reverse geocoding to find the address
      if (this.doReverseGeocoding) {
        this.geolocationSearchEngine.reverseGeocode([coords.lat, coords.lon])
      }
    },
    /**
     * This function is called when a suggestion was selected in the search field.
     */
    updateMap (event, searchResult) {
      // set the map to the new coordinates
      if (searchResult.properties.extent) {
        const bounds = searchResult.properties.extent
        this.currentBounds = [[bounds[1], bounds[0]], [bounds[3], bounds[2]]]
      } else {
        this.currentZoom = 17
      }

      // update the address data
      if (!this.differentLocation) {
        this.currentCoords = { lat: searchResult.geometry.coordinates[1], lon: searchResult.geometry.coordinates[0] }

        const prop = searchResult.properties
        if (prop.postcode) {
          this.currentPostal = prop.postcode
        }
        if (prop.city) {
          this.currentCity = prop.city
        }
        if (prop.street) {
          this.currentStreet = prop.street + (prop.housenumber ? ' ' + prop.housenumber : '')
        }
      }
      this.emitAddressChange()
    },
    emitAddressChange () {
      this.$emit('address-change', this.currentCoords, this.currentStreet, this.currentPostal, this.currentCity)
    },
  },
}
</script>

<style scoped>

</style>
