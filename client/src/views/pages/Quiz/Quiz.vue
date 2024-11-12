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
      :ok-disabled="requiredDataPrivacyNotice && ! isDataPrivacyNoticeAccepted"
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

      <b-alert v-if="requiredDataPrivacyNotice" show>
        {{ $i18n(`quiz.startmodal.privacyNotice.${requiredDataPrivacyNotice}`) }}
        <b-form-checkbox v-model="isDataPrivacyNoticeAccepted" class="mt-2">
          {{ $i18n('quiz.startmodal.acceptPrivacyNotice') }}
        </b-form-checkbox>
      </b-alert>
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
import { QUIZ_ID, SESSION_STATUS } from '@/consts'

export default {
  components: { MainQuizContainer, QuizConfirmationContainer, Container, QuizModal, QuizResults },
  props: {
    quizId: { type: Number, required: true },
  },
  data () {
    const hasUnlimitedTries = this.quizId === QUIZ_ID.HYGIENE
    const triesKey = hasUnlimitedTries ? 'unlimited_tries' : 'limited_tries'
    return {
      infoKeys: ['wiki', 'real_life_examples', triesKey, 'alone', 'read_carefully', 'multiple_choice', 'comment', 'pause', 'feedback'],
      isTimed: undefined,
      isFetching: false,
      status: null,
      isQuizModalShown: false,
      timeOutTimer: null,
      results: null,
      quiz: null,
      isDataPrivacyNoticeAccepted: false,
    }
  },
  computed: {
    isReady () {
      return this.status && this.quiz
    },
    canViewResults () {
      return Boolean(this.status.lastSessionStatus)
    },
    canFinalize () {
      return this.status.lastSessionStatus === SESSION_STATUS.PASSED && this.status.confirmed === false
    },
    requiredDataPrivacyNotice () {
      return (this.quizId === QUIZ_ID.HYGIENE) ? 'hygiene' : null
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
      if (this.status.lastSessionStatus !== SESSION_STATUS.RUNNING) {
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
      this.displayResults()
    },
  },
}
</script>
