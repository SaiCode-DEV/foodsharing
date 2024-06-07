<template>
  <Container
    v-if="!isReady"
    wrap-content
    :collapsible="false"
  >
    <template #title>
      <b-skeleton width="35%" />
    </template>
    <b-skeleton width="85%" />
    <b-skeleton width="55%" />
    <b-skeleton width="70%" />
  </Container>
  <div v-else>
    <MainQuizContainer
      :quiz="quiz"
      :status="status"
      :is-quiz-modal-shown="isQuizModalShown"
      @start-quiz="showStartModal"
    />

    <Container
      v-if="canViewResults"
      ref="resultsContainer"
      :tag="null"
      :title="$i18n('quiz.show_results')"
      :container-is-expanded="false"
      :wrap-content="true"
      @expand="displayResults"
    >
      <QuizResults :results="results" />
    </Container>

    <QuizConfirmationContainer
      v-if="canFinalize"
      :quiz-id="quiz.id"
    />

    <b-modal
      ref="start-info-modal"
      :title="$i18n('quiz.startmodal.title')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.start')"
      centered
      size="lg"
      scrollable
      @ok="initQuiz"
    >
      <ul>
        <li
          v-for="infoKey in infoKeys"
          :key="infoKey"
        >
          {{ $i18n(`quiz.startmodal.infos.${infoKey}`) }}
        </li>
      </ul>
    </b-modal>

    <QuizModal
      ref="quizModal"
      :visible.sync="isQuizModalShown"
      :quiz="quiz"
      :status="status"
      @update:questions-answered="(questionsAnswered) => status.questionsAnswered = questionsAnswered"
      @fetch-status="fetchStatus"
      @finished-quiz="onFinishedQuiz"
    />
  </div>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import QuizModal from '@/components/Quiz/QuizModal.vue'
import QuizResults from '@/components/Quiz/QuizResults.vue'
import MainQuizContainer from '@/components/Quiz/MainQuizContainer.vue'
import QuizConfirmationContainer from '@/components/Quiz/QuizConfirmationContainer.vue'
import { getQuizStatus, startQuiz, getQuizResults, getQuiz } from '@/api/quiz'
import { QUIZ_STATUS } from '@/consts'

export default {
  components: { MainQuizContainer, QuizConfirmationContainer, Container, QuizModal, QuizResults },
  props: {
    quizId: { type: Number, required: true },
  },
  data: () => ({
    infoKeys: ['wiki', 'real_life_examples', 'limited_tries', 'alone', 'read_carefully', 'multiple_choice', 'comment', 'pause', 'feedback'],
    isTimed: undefined,
    isFetching: false,
    status: null,
    isQuizModalShown: false,
    timeOutTimer: null,
    results: null,
    quiz: null,
  }),
  computed: {
    isReady () {
      return this.status && this.quiz
    },
    canViewResults () {
      return ![QUIZ_STATUS.NEVER_TRIED, QUIZ_STATUS.RUNNING].includes(this.status.status)
    },
    canFinalize () {
      return QUIZ_STATUS.PASSED === this.status.status && this.status.confirmed === false
    },
  },
  async mounted () {
    this.fetchQuiz()
    this.fetchStatus()
  },
  methods: {
    async fetchStatus (resolve) {
      this.status = await getQuizStatus(this.quizId)
      resolve?.()
    },
    async fetchQuiz () {
      this.quiz = await getQuiz(this.quizId)
    },
    async fetchResults () {
      this.results = await getQuizResults(this.quiz.id)
    },
    async initQuiz () {
      if (QUIZ_STATUS.RUNNING !== this.status.status) {
        await startQuiz(this.quiz.id, this.isTimed)
      }
      this.$refs.quizModal.showNextQuestion()
      this.results = null
    },
    showStartModal (isTimed) {
      this.isTimed = isTimed
      this.$refs['start-info-modal'].show()
    },
    async displayResults () {
      if (!this.results) {
        await this.fetchResults()
        await this.$nextTick()
      }
      this.$refs.resultsContainer?.setExpanded?.(true)
    },
    onFinishedQuiz () {
      this.results = null
      this.displayResults(true)
    },
  },
}
</script>
