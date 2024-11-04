<template>
  <Container
    :title="$i18n('quiz.general.title')"
  >
    <div v-if="!quiz" class="list-group-item">
      <b-skeleton width="85%" />
      <b-skeleton width="55%" />
      <b-skeleton width="70%" />
    </div>
    <template v-else>
      <div class="list-group-item">
        <h4 v-text="quiz.name" />
        <p>
          <b v-text="$i18n('quiz.key_facts.to_pass')" />
          <span v-text="quizKeyFacts" />
        </p>

        <p>
          <b v-text="$i18n('desc')+':'" />
          <QuizDescription :quiz="quiz" />
        </p>
      </div>
      <ContainerButton
        v-if="canEdit"
        variant="success"
        text-key="quiz.general.edit"
        @click="$bvModal.show('editQuizModal')"
      />
      <ContainerButton
        variant="success"
        text-key="quiz.test"
        @click="initQuizTest"
      />
      <EditQuizModal
        v-if="canEdit"
        :quiz="quiz"
        @update="fetchQuiz()"
      />
      <QuizModal
        ref="quizModal"
        :visible.sync="isQuizModalShown"
        :quiz="quiz"
        :status="status"
        is-test
        @update:questions-answered="(questionsAnswered) => status.questionsAnswered = questionsAnswered"
        @fetch-status="fetchStatus"
        @finished-quiz="onFinishedQuiz"
      />
      <b-modal
        :title="$i18n('quiz.test_results')"
        :visible="!!results"
        ok-only
        size="lg"
        @hidden="results = null"
      >
        <QuizResults v-if="results" :results="results" />
      </b-modal>
    </template>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { getQuiz, getQuizResults, getQuizStatus, startQuiz } from '@/api/quiz'
import EditQuizModal from './Modals/EditQuizModal.vue'
import QuizDescription from '@/components/Quiz/QuizDescription.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import QuizModal from '@/components/Quiz/QuizModal.vue'
import QuizResults from '@/components/Quiz/QuizResults.vue'

export default {
  components: { Container, EditQuizModal, QuizDescription, ContainerButton, QuizModal, QuizResults },
  props: {
    quizId: { type: Number, required: true },
    canEdit: { type: Boolean, required: true },
  },
  data: () => ({
    quiz: null,
    isQuizModalShown: false,
    status: { },
    results: null,
  }),
  computed: {
    quizKeyFacts () {
      return this.$i18n(`quiz.key_facts.${this.quiz.questionCountUntimed ? 'untimed' : 'timed'}`, this.quiz)
    },
  },
  mounted: function () {
    this.fetchQuiz()
  },
  methods: {
    async fetchQuiz () {
      this.quiz = await getQuiz(this.quizId)
    },
    async initQuizTest () {
      this.status = { }
      await startQuiz(this.quiz.id, true, true)
      this.$refs.quizModal.showNextQuestion()
    },
    async fetchStatus (resolve) {
      this.status = await getQuizStatus(this.quizId, true)
      resolve?.()
    },
    async onFinishedQuiz () {
      this.results = await getQuizResults(this.quizId, true)
    },
  },
}

</script>
