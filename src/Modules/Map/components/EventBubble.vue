<template>
  <map-popup id="eventBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3>{{ name }}</h3>
    </template>

    <div class="d-flex time-container mb-3">
      <CalendarDate v-if="!hasMultipleDays" :date-object="new Date(startDate)" />
      <b v-text="$t('events.span', { from: displayDate(startDate), until: displayDate(endDate) })" />
    </div>

    <Markdown :source="description" />

    <template #popup-footer>
      <a
        v-if="!loading"
        class="btn btn-primary"
        type="button"
        :href="$url('event', id)"
        v-text="$t('map.events.go')"
      />
    </template>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getEventBubbleContent } from '@/api/map'
import MapBubbleMixin from './MapBubbleMixin'
import CalendarDate from '@/components/CalendarDate.vue'

export default {
  components: { Markdown, CalendarDate },
  mixins: [MapBubbleMixin],
  data: () => ({
    id: null,
    name: '',
    description: '',
    startDate: null,
    endDate: null,
  }),
  computed: {
    hasMultipleDays () {
      if (!this.startDate || !this.endDate) return null
      return !this.$dateFormatter.isSame(this.endDate, this.startDate)
    },
  },
  methods: {
    async show (regionId) {
      await this.timedFetchAction(
        getEventBubbleContent(regionId),
        'eventBubbleModal',
        (data) => { Object.assign(this, data) },
      )
    },
    displayDate (date) {
      const params = { hour: 'numeric', minute: 'numeric' }
      if (this.hasMultipleDays) {
        Object.assign(params, { day: 'numeric', month: 'short' })
      }
      const time = this.$dateFormatter.format(date, params)
      return this.$t('date.time', { time })
    },
  },
}
</script>
<style lang="css" scoped>
.time-container {
  gap: 1em;
  align-items: center;
}
</style>
