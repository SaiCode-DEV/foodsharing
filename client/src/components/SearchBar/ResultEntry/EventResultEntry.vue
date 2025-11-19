<template>
  <a
    :href="$url('event', event.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-b-tooltip.noninteractive="$t(`search.results.event.invitation_tooltip.${+event.status}`)"
          :class="invitationIcon"
        />
        {{ event.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="event.region_id">
          {{ $t('search.results.in') }}
          <a :href="$url('events', event.region_id)">
            {{ event.region_name }}
          </a>
        </span>
        <span v-text="locationText" />
        <span v-text="timeText" />
      </small>
    </div>
  </a>
</template>
<script>
export default {
  props: {
    event: {
      type: Object,
      required: true,
    },
  },
  computed: {
    invitationIcon () {
      return 'fas fa-user-' + ['clock', 'check', 'clock', 'times'][+this.event.status]
    },
    locationText () {
      if (this.event.location_type === 1) { // TODO use const after MR !3387
        return this.$t('search.results.event.location_online')
      }
      return [
        this.event.location_name,
        this.event.location[0],
        `${this.event.location[1]} ${this.event.location[2]}`.trim(),
      ].filter(x => x).join(', ')
    },
    timeText () {
      const start = new Date(this.event.start)
      const end = new Date(this.event.end)
      const now = new Date()
      const dates = [start, end].map(date => this.$dateFormatter.date(date, { short: true }))
      const times = [start, end].map(date => this.$dateFormatter.time(date))
      if (dates[0] === dates[1]) {
        dates[1] = ''
      }
      const range = `${dates[0]} ${times[0]} bis ${dates[1]} ${times[1]}`
      const relativeTime = this.$dateFormatter.relativeTime((end < now) ? end : start)
      let relation = ''
      if (start > now) {
        relation = this.$t('search.results.time_relation.future')
      } else if (end < now) {
        relation = this.$t('search.results.time_relation.past')
      } else {
        relation = this.$t('search.results.time_relation.present_since')
      }
      return `${range} (${relation} ${relativeTime})`
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
