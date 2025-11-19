<template>
  <div>
    <p v-if="question.isMandatory">
      <b v-text="$t('quiz.mandatory_question')" />
    </p>
    <p>
      <b>{{ $t(`quiz.question`) }}:</b>
      {{ question.text }}
    </p>
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

export default {
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
