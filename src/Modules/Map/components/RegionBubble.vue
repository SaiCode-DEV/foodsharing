<template>
  <map-popup id="regionBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3>{{ name }}</h3>
    </template>

    <div class="card rounded">
      <Markdown :source="description" />
    </div>

    <template #popup-footer>
      <router-link
        v-if="!loading"
        class="btn btn-primary"
        type="button"
        :to="$url('publicRegion', id)"
      >
        {{ $t('map.region.go') }}
      </router-link>
    </template>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getRegionBubbleContent } from '@/api/map'
import MapBubbleMixin from './MapBubbleMixin'

export default {
  components: { Markdown },
  mixins: [MapBubbleMixin],
  data: () => ({
    id: null,
    name: '',
    description: '',
  }),
  methods: {
    async show (regionId) {
      await this.timedFetchAction(
        getRegionBubbleContent(regionId),
        'regionBubbleModal',
        (data) => { Object.assign(this, data) },
      )
    },
  },
}
</script>
