<template>
  <Container
    :tag="title"
    :title="$i18n(title)"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <RegionField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
    />
  </Container>
</template>

<script>
// Stores
import { useRegionStore } from '@/stores/regions'
// Components
import Container from '../Container.vue'
import RegionField from './RegionField'
// Mixin
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

const regionStore = useRegionStore()

export default {
  name: 'RegionList',
  components: {
    Container,
    RegionField,
  },
  mixins: [ListToggleMixin],
  props: {
    title: { type: String, default: 'dashboard.my.regions' },
  },
  computed: {
    data () {
      const data = regionStore.regions
      this.setList(data)
      return data
    },
  },
}
</script>
