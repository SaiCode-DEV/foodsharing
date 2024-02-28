<!-- Wrapper for LeafletLocationSearch to embed it into a vForm. Can be removed once the vForms have been replaced by vue. -->
<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
  <div id="input-wrapper" class="input-wrapper">
    <label class="wrapper-label ui-widget">{{ $i18n('addresspicker.label') }}</label>

    <div class="element-wrapper">
      <LeafletLocationSearch
        :zoom="zoom"
        :coordinates="currentCoordinates"
        :postal-code="currentPostalCode"
        :street="currentStreet"
        :city="currentCity"
        :icon-name="iconName"
        :icon-color="iconColor"
        :show-address-fields="showAddressFields"
        :additional-info-text="additionalInfoText"
        :disabled="disabled"
        @address-change="onAddressChanged"
      />
      <input
        name="lat"
        :value="currentCoordinates.lat"
        type="hidden"
      >
      <input
        name="lon"
        :value="currentCoordinates.lon"
        type="hidden"
      >
      <input
        name="anschrift"
        :value="currentStreet"
        type="hidden"
      >
      <input
        name="plz"
        :value="currentPostalCode"
        type="hidden"
      >
      <input
        name="ort"
        :value="currentCity"
        type="hidden"
      >
    </div>
  </div>
</template>

<script>
import LeafletLocationSearch from './LeafletLocationSearch'

export default {
  components: { LeafletLocationSearch },
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
    disabled: { type: Boolean, default: false },
  },
  data () {
    return {
      currentCoordinates: this.coordinates,
      currentStreet: this.street,
      currentPostalCode: this.postalCode,
      currentCity: this.city,
    }
  },
  methods: {
    onAddressChanged (coordinates, street, postalCode, city) {
      this.currentCoordinates = coordinates
      this.currentStreet = street
      this.currentPostalCode = postalCode
      this.currentCity = city
      this.$emit('address-change', this.currentCoordinates, this.currentStreet, this.currentPostalCode, this.currentCity)
    },
  },
}
</script>

<style lang="scss">

</style>
