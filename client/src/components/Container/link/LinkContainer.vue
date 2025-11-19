<template>
  <Container
    :tag="title"
    :title="$t(title)"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <LinkField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
      :type="type"
    />
  </Container>
</template>

<script>
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

import Container from '../Container.vue'
import LinkField from './LinkField.vue'

export default {
  name: 'LinkList',
  components: {
    Container,
    LinkField,
  },
  mixins: [ListToggleMixin],
  props: {
    title: { type: String, default: 'home.home' },
    type: { type: String, default: 'home' },
  },
}
</script>
