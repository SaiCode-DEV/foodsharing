<template>
  <Container
    :tag="title"
    :title="$t(title)"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <GroupField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
    />
  </Container>
</template>

<script>
// Stores
import { getters } from '@/stores/groups'
// Components
import Container from '../Container.vue'
import GroupField from './GroupField'
// Mixin
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

export default {
  name: 'GroupList',
  components: {
    Container,
    GroupField,
  },
  mixins: [ListToggleMixin],
  props: {
    title: { type: String, default: 'dashboard.my.groups' },
  },
  computed: {
    data () {
      const data = getters.get()
      this.setList(data)
      return data
    },
  },
}
</script>
