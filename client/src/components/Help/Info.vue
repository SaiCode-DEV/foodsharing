<template>
  <!--
    Please make sure not to create duplicates of wiki-articles.
    This should only be used for small tips or include a link to the wiki article.
  -->
  <i
    v-b-tooltip="$t('help.tooltip')"
    class="fas fa-question-circle p-2 cursor-help"
    @click.stop.prevent="$refs.modal.show"
  >
    <b-modal
      ref="modal"
      :title="$t('help.helpTitle', { title: $t(`help.key.${infoKey}.title`, props) })"
      centered
      :ok-title="$t('button.close')"
      ok-only
    >
      <Markdown :source="$t(`help.key.${infoKey}.md`, props)" />
    </b-modal>
  </i>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    infoKey: { type: String, required: true },
    props: { type: Object, default: null },
  },
}
</script>
<style scoped>
.cursor-help {
  cursor: help;
}
</style>
