<template>
  <div class="information-field alert alert-info d-flex">
    <i class="fas info-icon align-self-center mr-3 fa-flag-checkered" />
    <div class="flex-grow-1">
      <h4 v-text="$t('quiz.confirm')" />
      <Markdown :source="$t('quiz.confirmDashboardText', { url: $url('rise_role', quizConfirmation) })" />
    </div>
  </div>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    quizConfirmation: { type: Number, required: true },
  },
}
</script>

<style lang="scss" scoped>
.information-field {
  min-height: 100px;
}

.info-icon {
  font-size: 3rem;
}

::v-deep.markdown a {
  text-decoration: underline;
}
</style>
