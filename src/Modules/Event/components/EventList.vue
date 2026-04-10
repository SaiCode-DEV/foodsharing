<template>
  <Container :title="$t('events.bread')">
    <b-container class="p-2">
      <b-button
        variant="primary"
        :href="$url('eventAdd', regionId)"
      >
        {{ $t('events.add_new_event') }}
      </b-button>
    </b-container>
    <b-container>
      <div
        v-for="event in currentEvents"
        :key="event.id"
      >
        <EventPanel
          :event="event"
          :status="-1"
        />
      </div>
      <div class="card mt-3">
        <div class="card-header text-white bg-primary">
          {{ $t('events.past') }}
        </div>
        <div class="card-body">
          <div class="form-row p-1 mb-2">
            <label
              for="filter-input"
              class="col-form-label col-form-label-sm"
            >
              {{ $t('filter_by') }}
            </label>
            <b-form-input
              id="filter-input"
              v-model="filterText"
              type="text"
              class="form-control form-control-sm col-8"
              :placeholder="$t('name')"
            />
          </div>
          <div
            v-for="event in endedEvents"
            :key="event.id"
          >
            <EventPanel
              :event="event"
              :status="-1"
            />
          </div>
        </div>
      </div>
    </b-container>
  </Container>
</template>

<script>
import { optimizedCompare } from '@/utils'
import EventPanel from './EventPanel'
import EventsData from '@/stores/events'
import Container from '@/components/Container/Container.vue'

export default {
  components: { EventPanel, Container },
  props: {
    regionId: { type: Number, required: true },
  },
  data () {
    return {
      currentPage: 1,
      perPage: 20,
      filterText: null,
    }
  },
  computed: {
    events () {
      return this.sortEvents(EventsData.getters.getEvents(this.regionId))
    },
    currentEvents: function () {
      return this.events.filter(p => !this.isEventInPast(p))
    },
    endedEvents: function () {
      // select events that ended in the past, filter by name, and sort as new-to-old
      let filtered = this.events.filter(p => this.isEventInPast(p))

      const filterText = this.filterText ? this.filterText.toLowerCase() : null
      if (filterText) {
        filtered = filtered.filter(p => p.name.toLowerCase().indexOf(filterText) !== -1)
      }

      return filtered
    },
  },
  async created () {
    await EventsData.mutations.listEvents(this.regionId)
  },
  methods: {
    compare: optimizedCompare,
    isEventInPast (event) {
      return this.convertDate(event.endDate) < new Date()
    },
    convertDate (date) {
      return new Date(Date.parse(date))
    },
    sortEvents (eventsToSort) {
      // Return a sorted copy of the events list, newest first
      return eventsToSort.slice().sort((a, b) => this.convertDate(b.endDate) - this.convertDate(a.endDate))
    },
  },
}
</script>
