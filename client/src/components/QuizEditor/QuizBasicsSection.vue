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
      <button
        v-if="canEdit"
        class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
        @click="$bvModal.show('editQuizModal')"
      >
        {{ $i18n('quiz.general.edit') }}
        <EditQuizModal
          :quiz="quiz"
          @update="fetchQuiz()"
        />
      </button>
    </template>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { getQuiz } from '@/api/quiz'
import EditQuizModal from './Modals/EditQuizModal.vue'
import QuizDescription from '@/components/Quiz/QuizDescription.vue'

export default {
  components: { Container, EditQuizModal, QuizDescription },
  props: {
    quizId: { type: Number, required: true },
    canEdit: { type: Boolean, required: true },
  },
  data: () => ({
    quiz: null,
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
  },
}

</script>
