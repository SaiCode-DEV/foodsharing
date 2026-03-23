<template>
  <Container
    :tag="'basket.nearby'"
    :title="$t('basket.nearby')"
    :toggle-visiblity="baskets?.length > defaultAmount"
    @show-full-list="showFullList"
    @reduce-list="reduceList"
  >
    <template v-if="userHasLocation">
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
        v-text="$t('basket.no_nearby', {radius})"
      />
    </template>
    <small
      v-else
      class="list-group-item text-muted "
      v-text="$t('basket.nearby_requires_location')"
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
    userHasLocation () {
      return !!this.userStore.hasLocations
    },
  },
  async mounted () {
    if (!this.userHasLocation) return

    await this.basketStore.fetchNearby(this.userStore.getLocations)
    this.baskets = this.basketStore.getNearby()
    this.setList(this.baskets ?? [])
  },
}
</script>
