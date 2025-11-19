<template>
  <div class="list-group-item">
    <LeafletLocationSearch
      id="location"
      :zoom="17"
      :coordinates="location"
      :street="address.street"
      :postal-code="address.zipCode"
      :city="address.city"
      :disabled="!editMode"
      :marker-type="MARKER_TYPES.users"
      @address-change="onAddressChanged"
    />
    <div class="float-right">
      <b-button
        variant="primary"
        @click="$emit('prev')"
      >
        {{ $t('button.prev') }}
      </b-button>
      <b-button
        variant="primary"
        :disabled="!addressValid"
        @click.prevent="submit"
      >
        {{ $t('button.create') }}
      </b-button>
    </div>
  </div>
</template>

<script>
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import { MAP_CONSTANTS, MARKER_TYPES } from '@/stores/map'

export default {
  components: { LeafletLocationSearch },
  data () {
    return {
      editMode: true,
      location: { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON },
      address: { street: '', zipCode: '', city: '' },
    }
  },
  computed: {
    MARKER_TYPES: () => MARKER_TYPES,
    addressValid () {
      return this.address.street !== '' && this.address.zipCode !== '' && this.address.city !== ''
    },
  },
  methods: {
    submit () {
      this.$emit('update:location', this.location)
      this.$emit('update:address', this.address)
      this.$emit('submit')
    },
    onAddressChanged (coordinates, street, postalCode, city) {
      this.location = coordinates
      this.address.street = street
      this.address.zipCode = postalCode
      this.address.city = city
    },
  },
}
</script>
