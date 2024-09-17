<template>
  <Container
    :tag="userStore.hasLocations ? 'basket.nearby' : 'basket.recent'"
    :title="$i18n(userStore.hasLocations ? 'basket.nearby' : 'basket.recent')"
    :toggle-visiblity="data.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <BasketField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
    />
    <small
      v-if="filteredList.length === 0"
      class="list-group-item text-muted"
      v-text="$i18n('basket.no_nearby', {radius})"
    />
  </Container>
</template>
<script>
// Stores
import { getters } from '@/stores/baskets'
import { useUserStore } from '@/stores/user'
// Components
import Container from '../Container.vue'
import BasketField from './BasketField'
// Mixin
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

const userStore = useUserStore()

export default {
  components: {
    Container,
    BasketField,
  },
  mixins: [ListToggleMixin],
  props: {
    title: { type: String, default: 'dashboard.pickupdates' },
  },
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    radius () {
      return getters.getRadius()
    },
    data () {
      const data = getters.getNearby()
      this.setList(data)
      return data
    },
  },
}
</script>
