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
import { SESSION_STATUS } from '@/consts'
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
      return !( // Not allowed when:
        Boolean(this.status.currentWaitTime) || // pause or disqualified
        this.status.lastSessionStatus === SESSION_STATUS.RUNNING || // running
        (this.status.lastSessionStatus === SESSION_STATUS.PASSED && this.status.expirationTime === -1) // passed and not expiring
      )
    },
    isRunning () {
      return this.status.lastSessionStatus === SESSION_STATUS.RUNNING
    },
    statusName () {
      if (!this.status) return ''
      if (this.status.currentWaitTime === -1) return 'disqualified'
      if (this.status.currentWaitTime > 1) return 'pause'
      if (this.status.lastSessionStatus === SESSION_STATUS.RUNNING) return 'continue'
      return 'start'
    },
    stateBasedInfo () {
      if (!this.status) return ''

      // shorthands
      const i18n = (key, props) => this.$i18n('quiz.stateBasedInfo.' + key, props)
      const s = this.status

      if (s.currentWaitTime === -1) return i18n('disqualified')
      if (s.currentWaitTime > 1) return i18n('pause', s)
      if (s.lastSessionStatus === null) return i18n('neverTried')
      if (s.lastSessionStatus === SESSION_STATUS.RUNNING) return i18n('running', s)
      if (s.waitTimeAfterFailure === -1) return i18n('disqualifiedAfter')

      const after = s.waitTimeAfterFailure > 0 ? i18n('waitAfter', s) : i18n('noWaitAfter')
      if (s.lastSessionStatus === SESSION_STATUS.PASSED) {
        if (s.expirationTime === -1) return i18n('passed')
        const expire = s.expirationTime > 0 ? i18n('willExpire', s) : i18n('expired')
        return `${i18n('passedBefore')} ${expire} ${after}`
      }
      if (s.currentWaitTime !== null) return `${i18n('pauseElapsed')} ${after}`
      if (s.expirationTime > 0) return `${i18n('passedBefore')} ${i18n('willExpire')} ${after}`
      return `${i18n('failed')} ${after}`
    },
  },
}
</script>
