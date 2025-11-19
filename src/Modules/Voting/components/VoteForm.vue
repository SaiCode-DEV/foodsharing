<template>
  <div class="bootstrap">
    <b-form
      @submit="showConfirmDialog"
    >
      <b-alert
        :show="shuffledOptions.length > 1"
        variant="info"
      >
        <i v-if="poll.shuffleOptions" class="fas fa-random mr-2" />
        <span v-if="poll.shuffleOptions" v-text="$t('poll.hint_random_order')" />
        <span v-else v-text="$t('poll.hint_sorted_order')" />
      </b-alert>
      <SingleSelectionVotingComponent
        v-if="poll.type===0"
        :options="shuffledOptions"
        :enabled="mayVote"
        @update-valid-selection="updateValidSelection"
        @update-voting-request-values="updateVotingRequestValues"
      />
      <MultiSelectionVotingComponent
        v-else-if="poll.type===1"
        :options="shuffledOptions"
        :enabled="mayVote"
        @update-valid-selection="updateValidSelection"
        @update-voting-request-values="updateVotingRequestValues"
      />
      <ThumbVotingComponent
        v-else-if="poll.type===2"
        :options="shuffledOptions"
        :enabled="mayVote"
        @update-valid-selection="updateValidSelection"
        @update-voting-request-values="updateVotingRequestValues"
      />
      <ScoreVotingComponent
        v-else-if="poll.type===3"
        :options="shuffledOptions"
        :enabled="mayVote"
        @update-valid-selection="updateValidSelection"
        @update-voting-request-values="updateVotingRequestValues"
      />

      <b-alert
        v-if="mayVote"
        show
        variant="warning"
      >
        {{ $t('poll.submit_vote_warning') }}
      </b-alert>
      <b-button
        v-if="mayVote"
        type="submit"
        variant="primary"
        :disabled="!isValidSelection"
      >
        {{ $t('poll.submit_vote') }}
      </b-button>
    </b-form>

    <b-modal
      ref="confirmModal"
      :title="$t('poll.submit_vote')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="submitVote"
    >
      {{ $t('poll.submit_vote_question') }}
    </b-modal>
  </div>
</template>

<script>
import { BButton, BForm, BAlert, BModal } from 'bootstrap-vue'
import ThumbVotingComponent from './ThumbVotingComponent'
import ScoreVotingComponent from './ScoreVotingComponent'
import SingleSelectionVotingComponent from './SingleSelectionVotingComponent'
import MultiSelectionVotingComponent from './MultiSelectionVotingComponent'
import { vote } from '@/api/voting'
import { pulseError, pulseSuccess, shuffle } from '@/script'
import i18n from '@/helper/i18n'
import { HTTP_RESPONSE } from '@/consts'

export default {
  components: {
    ThumbVotingComponent,
    ScoreVotingComponent,
    SingleSelectionVotingComponent,
    MultiSelectionVotingComponent,
    BButton,
    BForm,
    BAlert,
    BModal,
  },
  props: {
    poll: {
      type: Object,
      required: true,
    },
    mayVote: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      isValidSelection: false,
      votingRequestValues: null,
    }
  },
  computed: {
    shuffledOptions: function () {
      return this.poll.shuffleOptions ? shuffle(this.poll.options) : this.poll.options
    },
  },
  methods: {
    showConfirmDialog (e) {
      e.preventDefault()
      this.$refs.confirmModal.show()
    },
    async submitVote (e) {
      this.isLoading = true
      this.isValidSelection = false
      try {
        await vote(this.poll.id, this.votingRequestValues)
        pulseSuccess(i18n('poll.vote_success'))
        this.$emit('vote-callback')
      } catch (e) {
        if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          pulseError(i18n('poll.error_cannot_vote'))
        } else {
          pulseError(i18n('error_unexpected'))
        }
      }

      this.isLoading = false
    },
    updateValidSelection (value) {
      this.isValidSelection = value
    },
    updateVotingRequestValues (value) {
      this.votingRequestValues = value
    },
  },
}
</script>
