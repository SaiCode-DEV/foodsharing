<template>
  <Container
    :tag="userStore.hasLocations ? 'basket.nearby' : 'basket.recent'"
    :title="$i18n(userStore.hasLocations ? 'basket.nearby' : 'basket.recent')"
    :toggle-visiblity="baskets?.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <BasketField
      v-for="(entry, key) in filteredList"
      :key="key"
      :entry="entry"
    />
    <div v-if="baskets === null" class="list-group-item d-flex">
      <b-skeleton width="40px" height="40px" />
      <div class="flex-grow-1 ml-2">
        <b-skeleton width="50%" />
        <b-skeleton width="80%" />
      </div>
    </div>
    <small
      v-else-if="filteredList.length === 0"
      class="list-group-item text-muted"
      v-text="$i18n('basket.no_nearby', {radius})"
    />
  </Container>
</template>
<script>
import { useBasketStore } from '@/stores/baskets'
import { useUserStore } from '@/stores/user'
import Container from '../Container.vue'
import BasketField from './BasketField'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'

export default {
  components: {
    Container,
    BasketField,
  },
  mixins: [ListToggleMixin],
  setup () {
    const userStore = useUserStore()
    const basketStore = useBasketStore()
    return {
      userStore,
      basketStore,
    }
  },
  data () {
    return { baskets: null }
  },
  computed: {
    radius () {
      return this.basketStore.getRadius
    },
  },
  async mounted () {
    await this.basketStore.fetchNearby(this.userStore.getLocations)
    this.baskets = this.basketStore.getNearby()
    this.setList(this.baskets ?? [])
  },
}
</script>
