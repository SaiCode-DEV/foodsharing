<template>
  <Container
    :tag="null"
    :title="$t('quiz.confirm')"
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
        v-if="content.confirm"
        v-model="accepted[content.id]"
      >
        {{ $t(content.confirm) }}
      </b-form-checkbox>
    </div>

    <button
      :disabled.attr="Object.values(accepted).some(x=>!x)"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="confirm"
      v-text="$t('button.confirm')"
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
    accepted: [],
  }),
  computed: {
    contentSources () {
      return {
        [QUIZ_ID.FOODSAVER]: [
          { contentId: CONTENT_IDS.CONFIRM_FOODSAVER_QUIZ, confirm: false },
          { contentId: CONTENT_IDS.LEGAL_FOODSAVER_QUIZ, confirm: 'foodsaver.upgrade.rv' },
        ],
        [QUIZ_ID.STORE_MANAGER]: [
          { contentId: CONTENT_IDS.CONFIRM_STORE_MANAGER_QUIZ, confirm: false },
          { contentId: CONTENT_IDS.LEGAL_STORE_MANAGER_QUIZ, confirm: 'foodsaver.upgrade.rv' },
          { contentId: CONTENT_IDS.PRIVACY_NOTICE_CONTENT, confirm: 'foodsaver.upgrade.pn' },
        ],
      }[this.quizId] ?? []
    },
  },
  async mounted () {
    for (const contentSource of this.contentSources) {
      const content = await getContent(contentSource.contentId)
      content.confirm = contentSource.confirm
      if (content.confirm) this.accepted[content.id] = false
      this.contents.push(content)
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
