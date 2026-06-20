<template>
  <div v-if="results">
    <h5>
      {{ $t(`quiz.results.title.${results.status}`) }}

      <TimeDisplay
        :time="results.endTime"
        class="float-right"
      />
    </h5>
    <p v-text="$t(`quiz.results.points.${results.status}`, results)" />
    <p v-if="!results.results">
      <i>{{ $t(`quiz.results.deleted`) }}</i>
    </p>
    <div
      v-for="(result, i) in results.results"
      :key="result.id"
      no-body
    >
      <b-card-header>
        <b-button
          v-b-toggle="`accordion-${i}`"
          block
          size="sm"
          class="result-detail-toggle"
        >
          <span>
            {{ $t(`quiz.question`) }} {{ i+1 }}
          </span>
          <b-badge
            pill
            :variant="result.userFailurePoints ? 'danger' : 'success'"
            class="failure-points-counter"
          >
            {{ $t(`quiz.fp_badge.${result.userFailurePoints ? 'wrong' : 'right'}`, result) }}
          </b-badge>
        </b-button>
      </b-card-header>
      <b-collapse
        :id="`accordion-${i}`"
        accordion="results-accordion"
      >
        <b-card-body>
          <QuestionDetails
            :question="result"
            :show-time="results.isTimed"
          />
          <p v-if="result.timedOut">
            <i class="fas fa-exclamation-triangle mr-1" />
            <b>{{ $t(`quiz.timed_out`) }}</b>
          </p>
          <Answers :answers="result.answers" />
          <Wikilink :link="result.wikilink" />
          <QuestionCommentField :question-id="result.id" />
        </b-card-body>
      </b-collapse>
    </div>
  </div>
</template>

<script>
import QuestionCommentField from './QuestionCommentField'
import Wikilink from './Wikilink'
import TimeDisplay from '@/components/TimeDisplay.vue'
import Answers from './Answers.vue'
import QuestionDetails from './QuestionDetails.vue'

export default {
  components: { QuestionCommentField, Wikilink, TimeDisplay, Answers, QuestionDetails },
  props: {
    results: { type: Object, default: () => {} },
  },
}
</script>

<style lang="scss" scoped>

.badge.failure-points-counter {
  float: right;
  top: .1em;
  font-size: 85%;
}

.result-detail-toggle span:nth-child(1) {
  float: left;
}
</style>
