<template>
  <div>
    <!-- eslint-disable vue/no-v-html -->
    <!-- Current values are valid, and cannot be changed without swapping to markdown. This can be removed after the next update, as soon as the values are updated. -->
    <div
      v-if="quiz.isDescriptionHtmlEncoded"
      v-html="quiz.description"
    />
    <Markdown
      v-else
      :source="quiz.description"
    />
  </div>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    quiz: { type: Object, required: true },
  },
}
</script>
