<template>
  <a
    :href="$url('poll', poll.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        {{ poll.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="poll.region_id">
          {{ $t('search.results.in') }}
          <a :href="$url('polls', poll.region_id)">
            {{ poll.region_name }}
          </a>
        </span>
        <span v-text="timeText" />
        <span v-if="voteText" v-text="voteText" />
      </small>
    </div>
  </a>
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
      const start = new Date(this.poll.start)
      const end = new Date(this.poll.end)
      const now = new Date()
      const dates = [start, end].map(date => this.$dateFormatter.date(date, { short: true }))
      const times = [start, end].map(date => this.$dateFormatter.time(date))
      if (dates[0] === dates[1]) {
        dates[1] = ''
      }
      const range = `${dates[0]} ${times[0]} bis ${dates[1]} ${times[1]}`
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
      const start = new Date(this.poll.start)
      const end = new Date(this.poll.end)
      const now = new Date()
      if (start > now || end < now) {
        return ''
      }
      const hasVotedCase = { null: 'not_eligible', false: 'can_vote', true: 'has_voted' }[this.poll.has_voted]
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
