<template>
  <Container
    v-if="data.length"
    tag="registered-pickups"
    :title="$i18n('dashboard.pickupdates')"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <PickupField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
    />
  </Container>
</template>

<script>
// Stores
import { usePickupStore } from '@/stores/pickups'

// Components
import Container from '../Container.vue'
import PickupField from './PickupField'
// Mixin
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

export default {
  name: 'RegionList',
  components: {
    Container,
    PickupField,
  },
  mixins: [ListToggleMixin],
  setup () {
    return {
      pickupStore: usePickupStore(),
    }
  },
  computed: {
    data () {
      const data = this.pickupStore.getRegistered
      this.setList(data)
      return data
    },
  },
  mounted () {
    this.pickupStore.fetchRegistered()
  },
}
</script>
