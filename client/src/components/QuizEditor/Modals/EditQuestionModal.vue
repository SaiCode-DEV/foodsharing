<template>
  <b-modal
    :id="modalId"
    :title="$t('quiz.editModal.question.title')"
    :ok-title="$t('button.save')"
    :cancel-title="$t('button.cancel')"
    :ok-disabled="!hasValidValues"
    scrollable
    centered
    size="lg"
    @ok="handleOk"
    @show="initializeFormData(question)"
  >
    <b-form>
      <b-form-group :label="$t('quiz.editModal.question.input.text.label')">
        <MarkdownInput
          :value.sync="form.text"
          conceal-toolbar
          variant="outline-primary"
          :rows="3"
          :placeholder="$t('quiz.editModal.question.input.text.placeholder')"
          :state="validities.text"
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.question.input.duration')">
        <b-form-select
          v-model.number="form.durationInSeconds"
          :options="durationOptions"
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.question.input.fp')">
        <b-form-select
          v-model.number="form.failurePoints"
          :options="failurePointsOptions"
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.question.input.wikilink.label')">
        <b-form-input
          v-model="form.wikilink"
          :placeholder="$t('quiz.editModal.question.input.wikilink.placeholder')"
          :state="validities.wikilink"
          trim.lazy
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.question.input.mandatory.label')">
        <b-form-checkbox
          v-model="form.isMandatory"
          :state="validities.isMandatory"
        >
          {{ $t('quiz.editModal.question.input.mandatory.text') }}
        </b-form-checkbox>
      </b-form-group>
    </b-form>
  </b-modal>
</template>

<script>
import { addQuestion, editQuestion } from '@/api/quiz'
import EditModalMixin from './EditModalMixin'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { MarkdownInput },
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
          text = `${min} ${this.$t('timepicker.labelMinutes')}`
          if (sec) {
            text += ', '
          }
        }
        if (sec) {
          text += `${sec} ${this.$t('timepicker.labelSeconds')}`
        }
        durationOptions.push({ value: i, text })
      }
      return durationOptions
    },
    failurePointsOptions () {
      return [0, 1, 2, 3, 12].map(failurePoints => ({
        value: failurePoints,
        text: this.$t('quiz.fp_options.' + ({ 0: 'joke', 12: 'ko' }[failurePoints] || 'default'), { failurePoints }),
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
