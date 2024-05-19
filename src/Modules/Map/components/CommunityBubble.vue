<template>
  <b-modal
    id="communityBubbleModal"
    ref="communityBubbleModal"
    scrollable
    centered
  >
    <template #modal-header="{ close }">
      <h3>{{ name }}</h3>
      <button
        type="button"
        class="btn btn-sm no-shadow"
        @click="close"
      >
        <i class="fas fa-xmark" />
      </button>
    </template>
    <template #modal-footer="{ hide }">
      <b-button variant="primary" @click="hide('forget')">
        {{ $i18n('globals.close') }}
      </b-button>
    </template>

    <div
      v-if="loading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div class="card mb-3 rounded">
      <Markdown :source="description" />
    </div>
  </b-modal>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getCommunityBubbleContent } from '@/api/map'
import { pulseError } from '@/script'

export default {
  components: { Markdown },
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
