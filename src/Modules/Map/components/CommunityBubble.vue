<template>
  <map-popup id="communityBubbleModal">
    <template #popup-header>
      <h3>{{ name }}</h3>
    </template>

    <div class="card mb-3 rounded">
      <Markdown :source="description" />
    </div>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getCommunityBubbleContent } from '@/api/map'
import { pulseError } from '@/script'
import MapPopup from './MapPopup.vue'

export default {
  components: { Markdown, MapPopup },
  data () {
    return {
      loading: true,
      name: '',
      description: '',
    }
  },
  methods: {
    async show (regionId) {
      this.loading = true
      this.$bvModal.show('communityBubbleModal')

      try {
        const bubbleData = await getCommunityBubbleContent(regionId)
        this.name = bubbleData.name
        this.description = bubbleData.description
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      this.loading = false
    },
  },
}
</script>
