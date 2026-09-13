<template>
  <map-popup id="foodSharePointBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3 v-if="!loading">
        {{ name }}
      </h3>
    </template>
    <template #popup-footer>
      <router-link
        v-if="!loading"
        class="btn btn-primary"
        type="button"
        :to="$url('foodsharepoint', id)"
      >
        {{ $t('map.foodsharepoint.go') }}
      </router-link>
    </template>

    <div v-if="picture" class="mb-3">
      <img class="picture rounded" :src="$url('upload', picture)">
    </div>
    <div class="card">
      <Markdown :source="description" />
    </div>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getFoodSharePointBubbleContent } from '@/api/map'
import MapBubbleMixin from './MapBubbleMixin'

export default {
  components: { Markdown },
  mixins: [MapBubbleMixin],
  data: () => ({
    id: null,
    name: '',
    description: '',
    picture: null,
  }),
  methods: {
    async show (foodSharePointId) {
      await this.timedFetchAction(
        getFoodSharePointBubbleContent(foodSharePointId),
        'foodSharePointBubbleModal',
        (data) => { Object.assign(this, data, { id: foodSharePointId }) },
      )
    },
  },
}
</script>

<style lang="scss" scoped>
.picture {
  width: 100%;
  overflow: hidden;
}
</style>
