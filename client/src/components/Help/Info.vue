<template>
  <!--
    Please make sure not to create duplicates of wiki-articles.
    This should only be used for small tips or include a link to the wiki article.
  -->
  <i
    v-b-tooltip="$i18n('help.tooltip')"
    class="fas fa-question-circle p-2 cursor-help"
    @click.stop="$refs.modal.show"
  >
    <b-modal
      ref="modal"
      :title="$i18n('help.helpTitle', { title: $i18n(`help.key.${infoKey}.title`) })"
      centered
      :ok-title="$i18n('button.close')"
      ok-only
    >
      <Markdown :source="$i18n(`help.key.${infoKey}.md`)" />
    </b-modal>
  </i>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    infoKey: { type: String, required: true },
  },
}
</script>
<style scoped>
.cursor-help {
  cursor: help;
}
</style>
