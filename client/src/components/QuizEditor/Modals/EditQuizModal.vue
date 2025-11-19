<template>
  <b-modal
    id="editQuizModal"
    :title="$t('quiz.editModal.quiz.title')"
    :ok-title="$t('button.save')"
    :cancel-title="$t('button.cancel')"
    :ok-disabled="!hasValidValues"
    scrollable
    centered
    size="lg"
    @ok="handleOk"
    @show="initializeFormData(quiz, { allowUntimed: !!quiz.questionCountUntimed})"
  >
    <b-form>
      <b-form-group :label="$t('quiz.editModal.quiz.input.name.label')">
        <b-form-input
          v-model="form.name"
          :placeholder="$t('quiz.editModal.quiz.input.name.placeholder')"
          required
          :state="validities.name"
          trim
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.quiz.input.maxFp')">
        <b-form-input
          v-model.number="form.maxFailurePointsToSucceed"
          type="number"
          min="0"
          max="20"
          required
          :state="validities.maxFailurePointsToSucceed"
        />
      </b-form-group>

      <b-form-group :label="$t('quiz.editModal.quiz.input.questionCountTimed')">
        <b-form-input
          v-model.number="form.questionCountTimed"
          type="number"
          min="1"
          max="25"
          required
          :state="validities.questionCountTimed"
        />
      </b-form-group>

      <b-form-group>
        <b-form-checkbox v-model="form.allowUntimed">
          {{ $t('quiz.editModal.quiz.input.allowUntimed') }}
        </b-form-checkbox>
      </b-form-group>

      <b-form-group
        v-if="form.allowUntimed"
        :label="$t('quiz.editModal.quiz.input.questionCountUntimed')"
      >
        <b-form-input
          v-model.number="form.questionCountUntimed"
          type="number"
          :min="form.questionCountTimed"
          max="50"
          required
          :state="validities.questionCountUntimed"
        />
      </b-form-group>
      <b-alert
        v-if="quiz.isDescriptionHtmlEncoded"
        variant="danger"
        show
      >
        <Markdown :source="$t('quiz.editModal.quiz.htmlWarning')" />
      </b-alert>
      <MarkdownInput
        :value.sync="form.description"
        :state="validities.description"
        :rows="6"
      />
    </b-form>
  </b-modal>
</template>
<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { editQuiz } from '@/api/quiz'
import EditModalMixin from './EditModalMixin'

export default {
  components: { MarkdownInput, Markdown },
  mixins: [EditModalMixin],
  props: {
    quiz: { type: Object, required: true },
  },
  computed: {
    validities () {
      return {
        name: Boolean(this.form.name),
        maxFailurePointsToSucceed: typeof this.form.maxFailurePointsToSucceed === 'number' && this.form.maxFailurePointsToSucceed >= 0,
        questionCountTimed: typeof (this.form.questionCountTimed) === 'number' && this.form.questionCountTimed > 0,
        questionCountUntimed: (typeof (this.form.questionCountUntimed) === 'number' &&
          this.form.questionCountTimed <= this.form.questionCountUntimed) || !this.form.allowUntimed,
        description: Boolean(this.form.description) && !(this.quiz.isDescriptionHtmlEncoded && this.form.description.includes('<')),
      }
    },
  },
  methods: {
    async handleOk () {
      if (!this.form.allowUntimed) {
        delete this.form.questionCountUntimed
      }
      delete this.form.allowUntimed
      delete this.form.isDescriptionHtmlEncoded
      delete this.form.id
      await editQuiz(this.quiz.id, this.form)
      this.$emit('update')
    },
  },
}
</script>
