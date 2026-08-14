<template>
  <b-modal
    :id="modalId"
    :title="$t('quiz.editModal.answer.title')"
    :ok-title="$t('button.save')"
    :cancel-title="$t('button.cancel')"
    :ok-disabled="!hasValidValues"
    scrollable
    centered
    size="lg"
    @ok="handleOk"
    @show="initializeFormData(answer)"
  >
    <b-form>
      <b-form-group :label="$t('quiz.editModal.answer.input.text.label')">
        <MarkdownInput
          :value.sync="form.text"
          conceal-toolbar
          variant="outline-primary"
          :rows="3"
          :placeholder="$t('quiz.editModal.answer.input.text.placeholder')"
          :state="validities.text"
        />
      </b-form-group>
      <b-form-group :label="$t('quiz.editModal.answer.input.explanation.label')">
        <MarkdownInput
          :value.sync="form.explanation"
          conceal-toolbar
          variant="outline-primary"
          :rows="3"
          :placeholder="$t('quiz.editModal.answer.input.explanation.placeholder')"
          :state="validities.explanation"
        />
      </b-form-group>
      <b-form-group :label="$t('quiz.editModal.answer.input.answerRating')">
        <b-form-select
          v-model.number="form.answerRating"
          :options="correctnessTypeOptions"
        />
      </b-form-group>
    </b-form>
  </b-modal>
</template>

<script>
import { editAnswer, addAnswer } from '@/api/quiz'
import EditModalMixin from './EditModalMixin'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { MarkdownInput },
  mixins: [EditModalMixin],
  props: {
    answer: { type: Object, required: true },
    modalId: { type: String, default: 'editAnswerModal' },
    questionId: { type: Number, required: true },
    quizId: { type: Number, required: true },
  },
  computed: {
    validities () {
      return {
        text: Boolean(this.form.text),
        explanation: Boolean(this.form.explanation),
      }
    },
    correctnessTypeOptions () {
      return [0, 1, 2].map(x => ({ text: this.$t('quiz.answers.short.' + x), value: x }))
    },
  },
  methods: {
    async handleOk () {
      if (this.answer.id) {
        delete this.form.id
        await editAnswer(this.quizId, this.questionId, this.answer.id, this.form)
      } else {
        await addAnswer(this.quizId, this.questionId, this.form)
      }
      this.$emit('update')
    },
  },
}
</script>
