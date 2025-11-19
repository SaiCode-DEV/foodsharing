<!-- Renders an entry from the content API as HTML -->
<template>
  <Container
    :hide-header="true"
    :tag="`content-${id}`"
    wrap-content
  >
    <div v-if="isLoading" class="ml-2 my-2">
      <b-skeleton width="85%" />
      <b-skeleton width="65%" />
      <b-skeleton width="70%" />
    </div>
    <div v-else-if="content">
      <h1>{{ content.title }}</h1>
      <!-- eslint-disable vue/no-v-html -->
      <!-- Content is already sanitised by the backend -->
      <div v-html="content.body" />
      <!-- eslint-enable -->
    </div>
  </Container>
</template>

<script>
import { getContent } from '@/api/content'
import { pulseError } from '@/script'
import Container from '@/components/Container/Container.vue'

export default {
  name: 'ContentEntry',
  components: { Container },
  props: {
    id: { type: Number, required: true },
  },
  data () {
    return {
      isLoading: false,
      content: null,
    }
  },
  async mounted () {
    this.isLoading = true

    try {
      this.content = await getContent(this.id)
    } catch (e) {
      pulseError(this.$t('content.error_loading') + ': ' + e.statusText)
    }

    this.isLoading = false
  },
}
</script>
