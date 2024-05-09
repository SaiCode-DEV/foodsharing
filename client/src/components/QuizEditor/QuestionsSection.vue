<template>
  <Container
    v-if="questions"
    :title="$i18n('quiz.questions_section.title', {count: questions.length})"
  >
    <div class="list-group-item">
      <div
        v-for="(question, i) in questions"
        :key="question.id"
        no-body
      >
        <b-card-header>
          <b-button
            v-b-toggle="`accordion-${i}`"
            block
            class="result-detail-toggle"
            variant="outline-primary"
          >
            <span class="question-title">
              {{ $i18n(`quiz.question`) }} #{{ question.id }}
              -
              {{ question.text }}
            </span>

            <b-badge
              v-if="question.commentCount"
              class="comment-badge"
              @click.stop="$bvModal.show(`wall-${i}`)"
            >
              {{ question.commentCount }}
              <i class="fas fa-comments" />
            </b-badge>

            <OverflowMenu
              :options="[
                {hide: !canEdit, icon:'pen', textKey: 'quiz.question_options.edit', callback: () => $bvModal.show(`editQuestionModal-${i}`)},
                {hide: !canEdit, icon:'plus-circle', textKey: 'quiz.question_options.add_answer', callback: () => addAnswerHandler(`addAnswerModal-${i}`)},
                {icon:'comments', textKey: 'quiz.question_options.show_comments', callback: () => $bvModal.show(`wall-${i}`)},
                {hide: !canEdit, icon:'trash', textKey: 'quiz.question_options.delete', callback: () => deleteQuestionHandler(question.id)},
              ]"
              :float="false"
            />

            <EditQuestionModal
              :modal-id="`editQuestionModal-${i}`"
              :question="question"
              :quiz-id="quizId"
              @update="fetchQuestions()"
            />

            <EditAnswerModal
              :modal-id="`addAnswerModal-${i}`"
              :answer="newAnswer"
              :question-id="question.id"
              :quiz-id="quizId"
              @update="fetchQuestions()"
            />
          </b-button>
        </b-card-header>
        <b-collapse
          :id="`accordion-${i}`"
          accordion="results-accordion"
        >
          <b-card-body>
            <QuestionDetails :question="question" />
            <Answers
              :answers="question.answers"
              :menu-options="[
                {hide: !canEdit, icon: 'pen', textKey: 'quiz.answer_options.edit', callback: (answerId) => $bvModal.show(`editAnswerModal-${answerId}`)},
                {hide: !canEdit, icon: 'trash', textKey: 'quiz.answer_options.delete', callback: (answerId) => deleteAnswerHandler(question.id, answerId)},
              ]"
            />
            <EditAnswerModal
              v-for="answer in question.answers"
              :key="answer.id"
              :modal-id="`editAnswerModal-${answer.id}`"
              :answer="answer"
              :question-id="question.id"
              :quiz-id="quizId"
              @update="fetchQuestions()"
            />
            <p>
              <b>{{ $i18n('wikilink') }}:</b>
              <a :href="question.wikilink">{{ question.wikilink }}</a>
            </p>
          </b-card-body>
        </b-collapse>
        <b-modal
          :id="`wall-${i}`"
          :title="$i18n('quiz.comment.wallTitle', question)"
          size="lg"
          scrollable
          centered
          hide-footer
          body-class="p-0"
          @close="fetchQuestions"
        >
          <Wall
            target="question"
            :target-id="question.id"
            hide-header
          />
        </b-modal>
      </div>
    </div>

    <button
      v-if="canEdit"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="$bvModal.show('addQuestionModal')"
    >
      {{ $i18n('quiz.question_options.add') }}
      <EditQuestionModal
        modal-id="addQuestionModal"
        :question="newQuestion"
        :quiz-id="quizId"
        @update="fetchQuestions()"
      />
    </button>
  </Container>
</template>

<script>
import { deleteAnswer, deleteQuestion, getQuestions } from '@/api/quiz'
import Container from '@/components/Container/Container.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import EditQuestionModal from './Modals/EditQuestionModal.vue'
import EditAnswerModal from './Modals/EditAnswerModal.vue'
import Wall from '@/components/Wall/Wall.vue'
import Answers from '@/components/Quiz/Answers.vue'
import QuestionDetails from '@/components/Quiz/QuestionDetails.vue'
import { ANSWER_RATING } from '@/consts'
import { pulseError } from '@/script'

export default {
  components: { Container, OverflowMenu, EditQuestionModal, EditAnswerModal, Wall, Answers, QuestionDetails },
  props: {
    quizId: { type: Number, required: true },
    canEdit: { type: Boolean, required: true },
  },
  data () {
    return {
      questions: null,
      newAnswer: { text: '', explanation: '', answerRating: ANSWER_RATING.RIGHT },
      newQuestion: { text: '', failurePoints: 1, durationInSeconds: 120, wikilink: this.$i18n('quiz.editModal.question.input.wikilink.placeholder') },
    }
  },
  mounted: function () {
    this.fetchQuestions()
  },
  methods: {
    async fetchQuestions () {
      this.questions = await getQuestions(this.quizId)
    },
    async deleteQuestionHandler (questionId) {
      if (await this.$confirmationDialogue('quiz.confirmDelete.question')) {
        try {
          await deleteQuestion(this.quizId, questionId)
          await this.fetchQuestions()
        } catch (error) {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
    },
    async deleteAnswerHandler (questionId, answerId) {
      if (await this.$confirmationDialogue('quiz.confirmDelete.answer')) {
        try {
          await deleteAnswer(this.quizId, questionId, answerId)
          await this.fetchQuestions()
        } catch (error) {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
    },
    addAnswerHandler (id) {
      this.$bvModal.show(id)
    },
  },
}
</script>

<style scoped lang="scss">
.question-title {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  flex-grow: 1;
  text-align: left;
}
.comment-badge {
  line-height: normal;
}
.result-detail-toggle {
  display: flex;
  &:hover .overflow-menu {
    color: white;
  }
}

.answer-edit {
  float: right;
  color: white !important;
  border-color: white  !important;
  background-color: transparent;

  &:hover {
    background-color: #fff2;
  }
  &:active {
    background-color: #fffa;
  }
}

</style>
