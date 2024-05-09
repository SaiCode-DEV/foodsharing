<template>
  <Container
    :title="$i18n(`quiz.title.${statusName}`, quiz)"
    :collapsible="false"
    :container-is-expanded="true"
  >
    <QuizDescription
      :quiz="quiz"
      class="list-group-item"
    />
    <div
      v-if="!isQuizModalShown"
      class="list-group-item"
    >
      <b>{{ stateBasedInfo }}</b>

      <!-- Alert can be removed after release "Laugenbrezel". Therefor not translated -->
      <b-alert
        v-if="isBeta || isDev"
        variant="danger"
        show
      >
        <i class="fas fa-exclamation-triangle" />
        Das Quiz-Modul wurde komplett neu gebaut. Alte und neue Quiz-Sessions sind nicht miteinander kompatibel! Bitte setze Quiz Sessions, die du in der Produktionsversion der Website gestartet hast, nicht auf Beta fort und umgekehrt.
      </b-alert>
    </div>

    <button
      v-if="!isQuizModalShown && canStart"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="$emit('start-quiz', true)"
      v-text="$i18n('quiz.timedstart', {count: quiz.questionCountTimed})"
    />
    <button
      v-if="!isQuizModalShown && canStart && quiz.questionCountUntimed"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="$emit('start-quiz', false)"
      v-text="$i18n('quiz.regstart', {count: quiz.questionCountUntimed})"
    />
    <button
      v-if="!isQuizModalShown && isRunning"
      class="list-group-item list-group-item-action list-group-item-secondary small font-weight-bold text-center"
      @click="$emit('start-quiz')"
      v-text="$i18n('quiz.continuenow')"
    />
  </Container>
</template>
<script>
import { QUIZ_STATUS } from '@/consts'
import Container from '@/components/Container/Container.vue'
import QuizDescription from './QuizDescription.vue'
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: { Container, QuizDescription },
  mixins: [RouteAndDeviceCheckMixin],
  props: {
    quiz: { type: Object, required: true },
    status: { type: Object, required: true },
    isQuizModalShown: { type: Boolean, default: false },
  },
  computed: {
    canStart () {
      return [QUIZ_STATUS.NEVER_TRIED, QUIZ_STATUS.FAILED, QUIZ_STATUS.PAUSE_ELAPSED].includes(this.status.status)
    },
    isRunning () {
      return this.status.status === QUIZ_STATUS.RUNNING
    },
    statusName () {
      return Object.keys(QUIZ_STATUS).find(key => QUIZ_STATUS[key] === this.status.status)
    },
    stateBasedInfo () {
      if (!this.statusName) return ''
      if ([QUIZ_STATUS.FAILED, QUIZ_STATUS.PAUSE_ELAPSED].includes(this.status.status)) {
        return this.$i18n(`quiz.state_based_info.${this.statusName}.${this.status.tries}`)
      }
      return this.$i18n(`quiz.state_based_info.${this.statusName}`, this.status)
    },
  },
}
</script>
