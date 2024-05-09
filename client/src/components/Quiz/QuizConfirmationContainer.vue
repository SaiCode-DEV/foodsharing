<template>
  <Container
    :tag="null"
    :title="$i18n('quiz.confirm')"
  >
    <div
      v-for="(content, i) in contents"
      :key="i"
      class="list-group-item"
    >
      <h4 v-text="content.title" />
      <!-- eslint-disable vue/no-v-html -->
      <!-- Sanitized in src/Modules/Content/ContentGateway.php getContent() -->
      <div v-html="content.body" />
      <b-form-checkbox
        v-if="i === contents.length - 1"
        v-model="accepted"
      >
        {{ $i18n('foodsaver.upgrade.rv') }}
      </b-form-checkbox>
    </div>

    <button
      :disabled.attr="!accepted"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="confirm"
      v-text="$i18n('button.confirm')"
    />
  </Container>
</template>
<script>
import { confirmQuiz } from '@/api/quiz'
import Container from '@/components/Container/Container.vue'
import { CONTENT_IDS, getContent } from '@/api/content'
import { QUIZ_ID } from '@/consts'

export default {
  components: { Container },
  props: {
    quizId: { type: Number, required: true },
  },
  data: () => ({
    contents: [],
    accepted: false,
  }),
  computed: {
    contentIDs () {
      return {
        [QUIZ_ID.FOODSAVER]: [CONTENT_IDS.CONFIRM_FOODSAVER_QUIZ, CONTENT_IDS.LEGAL_FOODSAVER_QUIZ],
        [QUIZ_ID.STORE_MANAGER]: [CONTENT_IDS.CONFIRM_STORE_MANAGER_QUIZ, CONTENT_IDS.LEGAL_STORE_MANAGER_QUIZ],
      }[this.quizId] ?? []
    },
  },
  async mounted () {
    for (const contentId of this.contentIDs) {
      this.contents.push(await getContent(contentId))
    }
  },
  methods: {
    async confirm () {
      await confirmQuiz(this.quizId)
      location.href = this.$url('dashboard')
    },
  },
}
</script>
