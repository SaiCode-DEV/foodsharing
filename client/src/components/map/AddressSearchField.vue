<!-- A text field combined with geocoding that allows searching for addresses. The selected suggestion is emitted as a 'change' event. -->
<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
  <b-form-input
    id="searchinput"
    v-model="searchInput"
    :placeholder="placeholder"
    :disabled="disabled"
  />
</template>

<script>
import { BFormInput } from 'bootstrap-vue'
import { locale } from '@/helper/i18n'
import { isTest } from '@/helper/server-data'

import $ from 'jquery'
import 'corejs-typeahead'
import 'typeahead-address-photon'

export default {
  name: 'AddressSearchField',
  components: { BFormInput },
  props: {
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
  },
  data () {
    return {
      searchInput: null,
      geolocationSearchEngine: null,
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
    /**
     * This function is called when a suggestion was selected in the search field. It maps the suggestion into a usable
     * format and emits the 'change' event.
     */
    updateMap (event, searchResult) {
      const coords = { lat: searchResult.geometry.coordinates[1], lon: searchResult.geometry.coordinates[0] }
      const bounds = searchResult.properties.extent
      const boundsCorners = bounds ? [[bounds[1], bounds[0]], [bounds[3], bounds[2]]] : null
      const address = {
        postcode: searchResult.properties.postcode,
        city: searchResult.properties.city,
        street: searchResult.properties.street,
      }
      this.$emit('change', coords, boundsCorners, address)
    },
  },
}
</script>

<style scoped>

</style>
