<template>
  <b-modal
    :visible="visible"
    :title="$t('quiz.infomodal.title', { index: status?.questionsAnswered+1, questions: status?.questionCount })"
    hide-header-close
    no-close-on-backdrop
    no-close-on-esc
    centered
    size="lg"
    scrollable
    @change="$emit('update:visible', $event.target.value)"
  >
    <div v-if="isFetching">
      <b-skeleton width="85%" />
      <b-skeleton width="55%" />
      <b-skeleton width="70%" />
    </div>
    <div v-else>
      <b-form-group
        class="question"
        :label="question?.text"
      >
        <p v-if="!isQuestionActive && !answeredInTime">
          <i class="fas fa-exclamation-triangle mr-1" />
          <b>{{ $t('quiz.timed_out') }}</b>
        </p>
        <div
          v-for="answer in question?.answers"
          :key="answer.id"
          class="answer-wrapper"
          :class="answerColorClass(answer.id)"
        >
          <b-form-checkbox
            v-model="selectedAnswers[answer.id]"
            :disabled="!isQuestionActive"
            @change="(newValue) => selectedAnswers['none'] &= !newValue"
          >
            {{ answer.text }}
          </b-form-checkbox>

          <div v-if="!isQuestionActive">
            <p class="my-3">
              <b>{{ $t(answerText(solutionById[answer.id]?.answerRating, selectedAnswers[answer.id])) }}</b>
            </p>
            <ExpandableExplanation
              :text="solutionById[answer.id].explanation"
            />
          </div>
        </div>
        <div
          v-if="isQuestionActive"
          ref="noneCorrect"
          class="answer-wrapper"
        >
          <b-form-checkbox
            v-model="selectedAnswers['none']"
            @change="noneSelectedHandler"
          >
            {{ $t('quiz.answers.none_correct') }}
          </b-form-checkbox>
        </div>
      </b-form-group>

      <Wikilink
        v-if="!isQuestionActive"
        :link="question.wikilink"
      />

      <QuestionCommentField
        v-if="!isQuestionActive"
        :question-id="question.id"
      />
    </div>
    <template
      v-if="isFetching"
      #modal-footer
    >
      <b-skeleton type="button" />
    </template>
    <template
      v-else
      #modal-footer
    >
      <div
        v-if="status?.isTimed && isQuestionActive"
        class="time-bar"
      >
        <div
          ref="time-left"
          class="time-left"
        />
      </div>
      <b-button
        v-if="(!isQuestionActive || !status?.isTimed ) && !isQuizFinished"
        variant="outline-primary"
        @click="closeQuiz"
      >
        {{ $t(isTest ? 'button.cancel' : 'quiz.button.pause') }}
      </b-button>
      <b-button
        v-if="!isQuizFinished"
        variant="primary"
        :disabled="isQuestionActive && (nothingSelected || !allOptionsSeen)"
        @click="continueQuizHandler"
      >
        {{ $t('button.next') }}
      </b-button>
      <b-button
        v-if="isQuizFinished"
        variant="primary"
        @click="finishQuiz"
      >
        {{ $t('quiz.button.finish') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script>

import { answerQuestion, getQuestion } from '@/api/quiz'
import { pulseError } from '@/script'
import QuestionCommentField from './QuestionCommentField'
import ExpandableExplanation from './ExpandableExplanation'
import Wikilink from './Wikilink'
import { ANSWER_RATING, HTTP_RESPONSE } from '@/consts'

export default {
  components: { QuestionCommentField, ExpandableExplanation, Wikilink },
  props: {
    quiz: { type: Object, required: true },
    status: { type: Object, required: true },
    visible: { type: Boolean, required: true },
    isTest: { type: Boolean, default: false },
  },
  data: () => ({
    isQuestionActive: true,
    selectedAnswers: {},
    question: null,
    questionStarted: null,
    solution: null,
    isFetching: false,
    answeredInTime: null,
    allOptionsSeen: false,
  }),
  computed: {
    isQuizFinished () {
      return !this.isQuestionActive && this.status.questionsAnswered + 1 >= this.status.questionCount
    },
    solutionById () {
      if (!this.solution) return {}
      return Object.fromEntries(this.solution.map(a => [a.id, a]))
    },
    nothingSelected () {
      return !Object.values(this.selectedAnswers).some(x => x)
    },
  },
  methods: {
    async handInQuestion () {
      this.isFetching = true
      window.clearTimeout(this.timeOutTimer)
      let selected = Object.entries(this.selectedAnswers).filter(a => a[1])
      if (selected.length === 0) { // Question was not answered at all
        selected = [null]
      } else { // Question was answered
        selected = selected.filter(a => a[0] !== 'none').map(a => +a[0])
      }
      const response = await answerQuestion(this.quiz.id, selected, this.isTest)
      this.solution = response.solution
      this.answeredInTime = !response.timedOut
      this.isQuestionActive = false
      this.isFetching = false
    },
    async showNextQuestion () {
      this.$emit('update:visible', true)
      this.isQuestionActive = true
      await Promise.all([
        new Promise(resolve => this.$emit('fetch-status', resolve)),
        this.fetchQuestion(),
      ])
      if (this.question?.timedOut) {
        pulseError(this.$t('quiz.timed_out_last'))
        this.$emit('update:questions-answered', this.status.questionsAnswered + 1)
      }
      this.animateTimer()
      this.questionStarted = Date.now()
      this.selectedAnswers = {}
    },
    async fetchQuestion () {
      if (this.isFetching) return
      this.isFetching = true
      try {
        const response = await getQuestion(this.quiz.id, this.isTest)
        this.question = response.question
        this.question.age = response.questionAge
        this.question.timedOut = response.timedOut
      } catch (error) {
        this.finishQuiz()
        if (error.code === HTTP_RESPONSE.FORBIDDEN) {
          pulseError(this.$t('quiz.timed_out_error'))
        } else {
          pulseError(this.$t('error_unexpected'))
        }
      }
      this.isFetching = false
      this.resetScrollRequirement()
    },
    async resetScrollRequirement () {
      this.allOptionsSeen = false
      await this.$nextTick()
      const targetElement = this.$refs.noneCorrect

      function onElementVisible ([element], observer) {
        if (element.isIntersecting) {
          this.allOptionsSeen = true
          observer.unobserve(targetElement)
        }
      }

      const observer = new IntersectionObserver(onElementVisible.bind(this), {
        root: null, // relative to the viewport
        threshold: 0.5, // trigger when at least half of the element is visible
      })

      observer.observe(targetElement)
    },
    async animateTimer () {
      await this.$nextTick()
      let duration = this.question?.durationInSeconds ?? 0
      let initialWidth = 1
      if (this.question?.age) {
        initialWidth -= this.question.age / this.question.durationInSeconds
        duration -= this.question.age
      }
      if (this.$refs['time-left']) {
        this.$refs['time-left'].animate(
          [{ width: initialWidth * 100 + '%' }, { width: '0%' }],
          { duration: duration * 1000, iterations: 1 },
        )
        this.timeOutTimer = window.setTimeout(this.timeOut, duration * 1000)
      }
    },
    finishQuiz () {
      this.closeQuiz()
      this.$emit('finished-quiz')
    },
    closeQuiz () {
      this.$emit('update:visible', false)
      this.$emit('fetch-status')
    },
    timeOut () {
      pulseError('Die Zeit ist um.')
      this.handInQuestion()
    },
    continueQuizHandler () {
      if (this.isQuestionActive) {
        this.handInQuestion()
        return
      }
      this.showNextQuestion()
    },
    answerColorClass (answerId) {
      const answerRating = this.solutionById[answerId]?.answerRating
      const selected = this.answeredInTime ? this.selectedAnswers[answerId] : true
      if (this.isQuestionActive) return ''
      if (answerRating === ANSWER_RATING.NEUTRAL) return 'neutral'
      if (!answerRating ^ selected) return 'success'
      return 'failure'
    },
    answerText (answerRating, selected) {
      const path = 'quiz.answers.'
      if (!this.answeredInTime) return `${path}timedOut.${answerRating}`
      if (answerRating === 2) return path + 'neutral'
      return `${path}${!!selected}_${!!answerRating}`
    },
    noneSelectedHandler (newValue) {
      if (newValue) {
        for (const key in this.selectedAnswers) {
          if (key !== 'none') this.selectedAnswers[key] = false
        }
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.time-bar {
  flex-grow: 1;
  height: 1em;
  border: 1px solid var(--fs-color-primary-500);
  border-radius: 1em;
  overflow: hidden;
  text-align:center;
  position: relative;
  span {
    position: absolute;
    transform: translate(-50%, -4px);
  }
}
.time-left {
  height: 100%;
  background-color: var(--fs-color-primary-500);
  width: 0%;
}

.answer-wrapper {
  padding: .5em 2em;
  border-radius: 1em;
  margin-bottom: 1em;
  ::v-deep .custom-control-label {
    color: currentColor;
  }

  &:not(.neutral) ::v-deep a {
    color: currentColor;
  }
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
<style lang="css">
.question legend, .answer-wrapper label {
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none
}
</style>
