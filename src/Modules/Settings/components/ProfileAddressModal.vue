<template>
  <b-modal
    ref="profileAddressModal"
    :title="$i18n('settings.address.title')"
    :cancel-title="$i18n('button.cancel')"
    :ok-title="$i18n('settings.address.choose')"
    centered
    modal-class="bootstrap"
    content-class="pr-3 pt-3"
    header-class="d-flex"
    @ok="$emit('update-location', { location: locationData, coordinate: coordinateData})"
  >
    <LeafletLocationSearchVForm
      :coordinates="coordinateData"
      :zoom="zoom"
      :street="locationData.street"
      :postal-code="locationData.postalCode"
      :city="locationData.city"
      @address-change="updateLocation"
    />
  </b-modal>
</template>

<script>
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm.vue'

export default {
  name: 'ProfileAddressModal',
  components: { LeafletLocationSearchVForm },
  props: {
    zoom: { type: Number, required: true },
    location: { type: Object, required: true },
    coordinate: { type: Object, required: true },

  },
  data () {
    return {
      locationData: this.location,
      coordinateData: this.coordinate,
    }
  },
  methods: {
    updateLocation (coordinates, street, postalCode, city) {
      this.locationData = { street: street, postalCode: postalCode, city: city }
      this.coordinateData = coordinates
    },
    show () {
      this.$refs.profileAddressModal.show()
    },
  },
}
</script>
