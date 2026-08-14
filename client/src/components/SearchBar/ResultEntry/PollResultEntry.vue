<template>
  <router-link
    :to="$url('poll', poll.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        {{ poll.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="poll.regionId">
          {{ $t('search.results.in') }}
          <router-link :to="$url('polls', poll.regionId)">
            {{ poll.regionName }}
          </router-link>
        </span>
        <span v-text="timeText" />
        <span v-if="voteText" v-text="voteText" />
      </small>
    </div>
  </router-link>
</template>
<script>
export default {
  props: {
    poll: {
      type: Object,
      required: true,
    },
  },
  computed: {
    timeText () {
      const start = new Date(this.poll.startAt)
      const end = new Date(this.poll.endAt)
      const now = new Date()
      const dates = [start, end].map(date => this.$dateFormatter.date(date, { short: true }))
      const times = [start, end].map(date => this.$dateFormatter.time(date))
      if (dates[0] === dates[1]) {
        dates[1] = ''
      }
      const range = `${dates[0]} ${times[0]} – ${dates[1]} ${times[1]}`
      const relativeTime = this.$dateFormatter.relativeTime((start > now) ? start : end)
      let relation = ''
      if (start > now) {
        relation = this.$t('search.results.time_relation.future')
      } else if (end < now) {
        relation = this.$t('search.results.time_relation.past')
      } else {
        relation = this.$t('search.results.time_relation.present_until')
      }
      return `${range} (${relation} ${relativeTime})`
    },
    voteText () {
      const start = new Date(this.poll.startAt)
      const end = new Date(this.poll.endAt)
      const now = new Date()
      if (start > now || end < now) {
        return ''
      }
      const hasVotedCase = { null: 'not_eligible', false: 'can_vote', true: 'has_voted' }[this.poll.hasVoted]
      return this.$t('search.results.poll.' + hasVotedCase)
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
