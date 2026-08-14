<template>
  <div>
    <p v-if="question.isMandatory">
      <b v-text="$t('quiz.mandatory_question')" />
    </p>
    <div>
      <b>
        {{ $t(`quiz.question`) }}:
      </b>
      <blockquote>
        <Markdown :source="question.text" />
      </blockquote>
    </div>
    <p v-if="showTime">
      <b>{{ $t(`quiz.timelimit`) }}:</b>
      {{ question.durationInSeconds + 's' }}
    </p>
    <p>
      <b>{{ $t('quiz.max_fp') }}:</b>
      {{ failurePointsDescription }}
    </p>
  </div>
</template>
<script>
import { ANSWER_RATING } from '@/consts'
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    question: { type: Object, required: true },
    showTime: { type: Boolean, default: true },
  },
  computed: {
    failurePointsDescription () {
      const valuedAnswers = this.question.answers.filter(answer => answer.answerRating !== ANSWER_RATING.NEUTRAL).length
      const params = {
        failurePoints: this.question.failurePoints,
        perMistake: Math.round(1e2 * this.question.failurePoints / (valuedAnswers || 1)) / 1e2,
      }
      return this.$t('quiz.fp_description', params)
    },
  },
}
</script>
