<template>
  <p>
    <b>{{ $i18n(`quiz.answers.name`) }}:</b>
    <span
      v-for="answer in sortedAnswers"
      :key="answer.id"
      class="result-answer-container"
    >
      <span :class="answerColorClass(answer)">
        <OverflowMenu
          :options="menuOptions"
          :callback-args="[answer.id]"
          :variant="menuVariant(answer)"
        />
        <b>{{ $i18n(answerText(answer)) }}</b><br>
        {{ answer.text }}
        <ExpandableExplanation :text="answer.explanation" />
      </span>
    </span>
  </p>
</template>
<script>
import OverflowMenu from '@/components/OverflowMenu.vue'
import ExpandableExplanation from './ExpandableExplanation.vue'
import { ANSWER_RATING } from '@/consts'

export default {
  components: { ExpandableExplanation, OverflowMenu },
  props: {
    answers: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
  },
  computed: {
    sortedAnswers () {
      return [...this.answers].sort((a, b) => a.answerRating - b.answerRating)
    },
  },
  methods: {
    answerColorClass (answer) {
      if (answer.answerRating === 2) return ['neutral']
      if (answer.timedOut) return ['failure', 'success', 'neutral'][answer.answerRating]
      if (!answer.answerRating ^ answer.selected) return 'success'
      return 'failure'
    },
    answerText (answer) {
      const path = 'quiz.answers.'
      if (answer.timedOut) return `${path}timedOut.${answer.answerRating}`
      if (answer.answerRating === 2) return path + 'neutral'
      return `${path}${!!answer.selected}_${!!answer.answerRating}`
    },
    menuVariant (answer) {
      return answer.answerRating === ANSWER_RATING.NEUTRAL ? 'dark' : 'light'
    },
  },
}
</script>
<style scoped lang="scss">
.result-answer-container > span {
  display: block;
  padding: .5em .75em;
  margin-bottom: .25em;
  border-radius: 1em;

  &:not(.neutral) ::v-deep a {
    color: currentColor;
  }
}
.mistake-icon {
  float: right;
}
.success {
  background-color: var(--fs-color-success-500);
  color:white;
}
.failure {
  background-color: var(--fs-color-danger-500);
  color:white;
}
.neutral {
  background-color: var(--fs-color-warning-200);
}
</style>
