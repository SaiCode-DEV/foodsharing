<template>
  <b-modal
    :id="modalId"
    :title="$i18n('quiz.editModal.answer.title')"
    :ok-title="$i18n('button.save')"
    :cancel-title="$i18n('button.cancel')"
    :ok-disabled="!hasValidValues"
    scrollable
    centered
    size="lg"
    @ok="handleOk"
    @show="initializeFormData(answer)"
  >
    <b-form>
      <b-form-group :label="$i18n('quiz.editModal.answer.input.text.label')">
        <b-form-textarea
          v-model="form.text"
          :placeholder="$i18n('quiz.editModal.answer.input.text.placeholder')"
          :state="validities.text"
          trim.lazy
          rows="3"
        />
      </b-form-group>
      <b-form-group :label="$i18n('quiz.editModal.answer.input.explanation.label')">
        <b-form-textarea
          v-model="form.explanation"
          :placeholder="$i18n('quiz.editModal.answer.input.explanation.placeholder')"
          :state="validities.explanation"
          trim.lazy
          rows="3"
        />
      </b-form-group>
      <b-form-group :label="$i18n('quiz.editModal.answer.input.answerRating')">
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

export default {
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
      return [0, 1, 2].map(x => ({ text: this.$i18n('quiz.answers.short.' + x), value: x }))
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
