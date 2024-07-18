<template>
  <map-popup id="foodSharePointBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3 v-if="!loading">
        {{ name }}
      </h3>
    </template>
    <template #popup-footer>
      <a
        v-if="!loading"
        class="btn btn-primary mx-5"
        type="button"
        :href="$url('foodsharepoint', id)"
        v-text="$i18n('map.foodsharepoint.go')"
      />
    </template>

    <div v-if="picture">
      <img class="picture" :src="picturePath">
    </div>
    <div class="card my-3">
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
  computed: {
    picturePath () {
      if (!this.picture) {
        return null
      } else if (this.picture.startsWith('/api/uploads')) {
        return this.picture
      } else {
        return '/images/' + this.picture.replace('/', '/crop_0_528_')
      }
    },
  },
  methods: {
    async show (foodSharePointId) {
      await this.timedFetchAction(
        getFoodSharePointBubbleContent(foodSharePointId),
        'foodSharePointBubbleModal',
        (data) => { Object.assign(this, data) },
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
