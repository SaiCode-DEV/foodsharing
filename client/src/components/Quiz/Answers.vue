<template>
  <p>
    <b>{{ $i18n(`quiz.answers.name`) }}:</b>
    <span
      v-for="answer in sortedAnswers"
      :key="answer.id"
      class="result-answer-container"
    >
      <span :class="answerColorClass(answer.answerRating)">
        <i
          v-if="'selected' in answer && !answer.timedOut && (answer.selected ^ answer.answerRating) === 1"
          v-b-tooltip="$i18n(`quiz.error_tooltip`)"
          class="fas fa-exclamation-triangle mistake-icon"
        />
        <OverflowMenu
          :options="menuOptions"
          :callback-args="[answer.id]"
          :variant="menuVariant(answer)"
        />
        <b>{{ $i18n(`quiz.answers.short.${answer.answerRating}`) }}:</b>
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
    answerColorClass (answerRating) {
      return ['failure', 'success', 'neutral'][answerRating]
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
