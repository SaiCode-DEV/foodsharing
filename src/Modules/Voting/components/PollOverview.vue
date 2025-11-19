<template>
  <Container
    :title="$t('poll.title', poll)"
    :collapsible="false"
    info-key="polls"
  >
    <div class="list-group-item">
      <b-alert :show="userAlreadyVoted" variant="info">
        <i class="fas fa-check-circle mr-2" />
        {{ $t('poll.already_voted') }}: {{ $dateFormatter.date(displayedVoteDate) }}
      </b-alert>
      <b-alert :show="isPollInFuture" variant="info">
        <i class="fas fa-clock mr-2" />
        {{ $t('poll.may_not_yet_vote') }}
      </b-alert>
      <b-alert :show="!userAlreadyVoted && !userMayVote && !isPollInPast && !isPollInFuture" variant="danger">
        <i class="fas fa-times-circle mr-2" />
        {{ $t('poll.may_not_vote') }}
      </b-alert>

      <ul class="poll-properties">
        <li class="poll-date">
          <b>{{ $t('poll.time_period') }}:</b>
          {{ $dateFormatter.dateTime(startDate) }} - {{ $dateFormatter.dateTime(endDate) }}
          <b-badge
            v-if="isPollInPast"
            pill
            variant="info"
          >
            {{ $t('poll.in_past') }}
          </b-badge>
          <b-badge
            v-else-if="isPollInFuture"
            pill
            variant="secondary"
          >
            {{ $t('poll.in_future') }}
          </b-badge>
        </li>
        <li class="poll-region">
          <b>{{ $t(isWorkGroup ? 'terminology.group' : 'terminology.region') }}:</b> <a :href="$url('polls', regionId)">{{ regionName }}</a>
        </li>
        <li class="poll-scope">
          <b>{{ $t('poll.allowed_voters') }}:</b> {{ $t('poll.scope_description_'+poll.scope) }}
        </li>
        <li class="poll-scope">
          <b>{{ $t('poll.eligible_votes_count') }}:</b> {{ poll.eligibleVotesCount }}
        </li>
        <li class="poll-type">
          <b>{{ $t('poll.type') }}:</b> {{ $t('poll.type_description_'+poll.type) }}
        </li>
        <li v-if="isPollInPast">
          <b>{{ $t('poll.results.percentage_of_votes') }}:</b> {{ percentageTurnout }} %
        </li>
      </ul>
      <div v-if="mayEdit">
        <b-link
          :href="$url('pollEdit', poll.id)"
          class="btn btn-sm btn-primary mb-3"
        >
          {{ $t('poll.edit.title') }}
        </b-link>
        <b-link
          class="btn btn-sm btn-primary mb-3"
          @click="showCancelConfirmDialog"
        >
          {{ $t('poll.cancel.title') }}
        </b-link>
      </div>
    </div>
    <div class="list-group-item">
      <Markdown :source="poll.description" />
    </div>
    <div class="list-group-item">
      <VoteForm
        v-if="!isPollInPast"
        :poll="poll"
        :may-vote="userMayVote"
        @vote-callback="userJustVoted"
      />

      <b-alert :show="Boolean(userVoteDate)" variant="info">
        <i class="fas fa-eye-slash mr-2" />
        {{ $t('poll.untraceable') }}
      </b-alert>

      <ResultsTable
        v-if="isPollInPast"
        :options="poll.options"
        :num-votes="poll.votes"
      />
    </div>
  </Container>
</template>

<script>
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import VoteForm from './VoteForm'
import ResultsTable from './ResultsTable'
import Markdown from '@/components/Markdown/Markdown'
import { deletePoll } from '@/api/voting'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import Container from '@/components/Container/Container.vue'

export default {
  components: { ResultsTable, VoteForm, Markdown, Container },
  props: {
    poll: {
      type: Object,
      required: true,
    },
    regionId: {
      type: Number,
      required: true,
    },
    regionName: {
      type: String,
      required: true,
    },
    isWorkGroup: {
      type: Boolean,
      default: false,
    },
    mayVote: {
      type: Boolean,
      default: false,
    },
    userVoteDate: {
      type: Object,
      default: null,
    },
    mayEdit: {
      type: Boolean,
      default: false,
    },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      userMayVote: this.mayVote,
      userAlreadyVoted: this.userVoteDate !== null,
      displayedVoteDate: this.userVoteDate ? new Date(Date.parse(this.userVoteDate.date)) : new Date(),
    }
  },
  computed: {
    startDate () {
      return new Date(Date.parse(this.poll.startDate.date))
    },
    endDate () {
      return new Date(Date.parse(this.poll.endDate.date))
    },
    isPollInPast () {
      return this.endDate < new Date()
    },
    isPollInFuture () {
      return this.startDate > new Date()
    },
    percentageTurnout () {
      return parseFloat(this.poll.votes / this.poll.eligibleVotesCount * 100).toFixed(2).toLocaleString()
    },
  },
  methods: {
    userJustVoted () {
      this.userAlreadyVoted = true
      this.userMayVote = false
    },
    async showCancelConfirmDialog (e) {
      e.preventDefault()
      if (!await this.confirmationDialogue('poll.cancel.question')) return
      showLoader()
      try {
        // cancel poll and redirect to poll list
        await deletePoll(this.poll.id)
        window.location.href = this.$url('polls', this.poll.regionId)
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
.prestyled {
  white-space: pre-line;
}

.poll-properties {
  font-size: 0.875rem;

  & > li {
    margin-bottom: 0.25rem;
  }
}

.card-body {
  hr {
    // counter the .card definition of padding: 6px 8px;
    margin-left: -8px;
    margin-right: -8px;
  }

  ::v-deep label {
    max-width: 100%;
  }
}
</style>
