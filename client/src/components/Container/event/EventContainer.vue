<template>
  <Container
    v-if="data.length > 0"
    :tag="title"
    :title="data.length > 1 ? $t(`${title}Count`, { count: data.length }) : $t(title)"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <EventField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
      :options="options"
    />
  </Container>
</template>

<script>
// Stores
import { getters } from '@/stores/events'
// Components
import Container from '../Container.vue'
import EventField from './EventField'
// Mixin
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

export default {
  name: 'EventList',
  components: {
    Container,
    EventField,
  },
  mixins: [ListToggleMixin],
  props: {
    title: { type: String, default: 'dashboard.event' }, // dashboard.event -> dashboard.eventCount
    options: { type: Boolean, default: false },
  },
  computed: {
    data () {
      let data = getters.getAccepted()
      if (this.options) {
        data = getters.getInvited()
      }
      this.setList(data)
      return data
    },
  },
}
</script>
