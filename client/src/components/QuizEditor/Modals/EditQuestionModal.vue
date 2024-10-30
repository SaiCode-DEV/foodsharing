<template>
  <b-modal
    :id="modalId"
    :title="$i18n('quiz.editModal.question.title')"
    :ok-title="$i18n('button.save')"
    :cancel-title="$i18n('button.cancel')"
    :ok-disabled="!hasValidValues"
    scrollable
    centered
    size="lg"
    @ok="handleOk"
    @show="initializeFormData(question)"
  >
    <b-form>
      <b-form-group :label="$i18n('quiz.editModal.question.input.text.label')">
        <b-form-textarea
          v-model="form.text"
          :placeholder="$i18n('quiz.editModal.question.input.text.placeholder')"
          :state="validities.text"
          trim.lazy
          rows="3"
        />
      </b-form-group>

      <b-form-group :label="$i18n('quiz.editModal.question.input.duration')">
        <b-form-select
          v-model.number="form.durationInSeconds"
          :options="durationOptions"
        />
      </b-form-group>

      <b-form-group :label="$i18n('quiz.editModal.question.input.fp')">
        <b-form-select
          v-model.number="form.failurePoints"
          :options="failurePointsOptions"
        />
      </b-form-group>

      <b-form-group :label="$i18n('quiz.editModal.question.input.wikilink.label')">
        <b-form-input
          v-model="form.wikilink"
          :placeholder="$i18n('quiz.editModal.question.input.wikilink.placeholder')"
          :state="validities.wikilink"
          trim.lazy
        />
      </b-form-group>
    </b-form>
  </b-modal>
</template>

<script>
import { addQuestion, editQuestion } from '@/api/quiz'
import EditModalMixin from './EditModalMixin'

export default {
  mixins: [EditModalMixin],
  props: {
    question: { type: Object, required: true },
    modalId: { type: String, default: 'editQuestionModal' },
    quizId: { type: Number, default: -1 },
  },
  computed: {
    validities () {
      return {
        text: Boolean(this.form.text),
        wikilink: /^https:\/\/[\d\w]+\.[\d\w]+/.test(this.form.wikilink),
      }
    },
    durationOptions () {
      const durationOptions = []
      for (let i = 10; i <= 180; i += 10) {
        const [min, sec] = [Math.floor(i / 60), i % 60]
        let text = ''
        if (min) {
          text = `${min} ${this.$i18n('timepicker.labelMinutes')}`
          if (sec) {
            text += ', '
          }
        }
        if (sec) {
          text += `${sec} ${this.$i18n('timepicker.labelSeconds')}`
        }
        durationOptions.push({ value: i, text })
      }
      return durationOptions
    },
    failurePointsOptions () {
      return [0, 1, 2, 3, 12].map(failurePoints => ({
        value: failurePoints,
        text: this.$i18n('quiz.fp_options.' + ({ 0: 'joke', 12: 'ko' }[failurePoints] || 'default'), { failurePoints }),
      }))
    },
  },
  methods: {
    async handleOk () {
      delete this.form.id
      delete this.form.answers
      delete this.form.commentCount
      if (this.question.id) {
        await editQuestion(this.quizId, this.question.id, this.form)
      } else {
        await addQuestion(this.quizId, this.form)
      }
      this.$emit('update')
    },
  },
}
</script>
