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
    <LeafletLocationSearch
      :coordinates="coordinateData"
      :zoom="zoom"
      :street="locationData.street"
      :postal-code="locationData.postalCode"
      :city="locationData.city"
      :marker-type="MARKER_TYPES.users"
      @address-change="updateLocation"
    />
  </b-modal>
</template>

<script>
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import { MARKER_TYPES } from '@/stores/map'

export default {
  name: 'ProfileAddressModal',
  components: { LeafletLocationSearch },
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
  computed: {
    MARKER_TYPES: () => MARKER_TYPES,
  },
  methods: {
    updateLocation (coordinates, street, postalCode, city) {
      this.locationData = { street, postalCode, city }
      this.coordinateData = coordinates
    },
    show () {
      this.$refs.profileAddressModal.show()
    },
  },
}
</script>
