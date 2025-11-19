<template>
  <map-popup id="communityBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3>{{ name }}</h3>
    </template>

    <div class="card mb-3 rounded">
      <Markdown :source="description" />
    </div>

    <template #popup-footer>
      <a
        v-if="!loading"
        class="btn btn-primary"
        type="button"
        :href="$url('publicRegion', id)"
        v-text="$t('map.community.go')"
      />
    </template>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getCommunityBubbleContent } from '@/api/map'
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
        getCommunityBubbleContent(regionId),
        'communityBubbleModal',
        (data) => { Object.assign(this, data) },
      )
    },
  },
}
</script>
