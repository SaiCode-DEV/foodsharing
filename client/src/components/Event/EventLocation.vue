<template>
  <Container
    :title="$i18n('events.location')"
  >
    <div class="list-group-item">
      <p v-if="event.locationDetails" v-text="event.locationDetails" />
      <div v-if="event.address || event.location" class="d-flex justify-content-between">
        <p v-if="event.address">
          <span v-text="event.address.street" /><br>
          <span v-text="event.address.zip" />
          <span v-text="event.address.city" />
        </p>
        <NavigateWithSelector
          v-if="event.location"
          :latitude="event.location.lat"
          :longitude="event.location.lon"
        />
      </div>
      <p v-if="event.type === EVENT_TYPE.ONLINE" v-text="enterOnlineText" />
    </div>

    <div v-if="event.location" class="list-group-item p-0">
      <LeafletMap
        :zoom="17"
        :center="event.location"
        height="400px"
      >
        <LMarker
          :lat-lng="{ lat: event.location.lat, lon: event.location.lon }"
          :icon="icon"
        />
      </LeafletMap>
    </div>

    <div
      v-if="!isOver && event.type === EVENT_TYPE.ONLINE"
      v-b-tooltip.bottom.ds500.noninteractive="mayEnter ? '' : $i18n('events.meeting.not_started')"
      class="list-group-item p-0 border-0"
    >
      <button
        class="list-group-item small list-group-item-secondary list-group-item-action list-group-item-action-toggle font-weight-bold text-center"
        :disabled="!mayEnter"
        @click="joinMeeting"
        v-text="'Beitreten'"
      />
    </div>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import LeafletMap from '@/components/map/LeafletMap.vue'
import { LMarker } from 'vue2-leaflet'

import ConferenceOpenerMixin from '@/mixins/ConferenceOpenerMixin'
import { EVENT_TYPE } from '@/consts'

import Leaflet from 'leaflet'
import NavigateWithSelector from '../UI/NavigateWithSelector.vue'
Leaflet.AwesomeMarkers.Icon.prototype.options.prefix = 'fa'

// defines, how much earlier or later people may join online events via the button.
const enterEarlyBuffer = 15 * 60 * 1000 // 15 minutes
const enterLateBuffer = 60 * 60 * 1000 // 1 hour

export default {
  components: { Container, LeafletMap, LMarker, NavigateWithSelector },
  mixins: [ConferenceOpenerMixin],
  props: {
    event: { type: Object, required: true },
  },
  data: () => ({
    currentTime: new Date(),
    EVENT_TYPE,
  }),
  computed: {
    icon () {
      return Leaflet.AwesomeMarkers.icon({ icon: 'calendar-alt', markerColor: 'green' })
    },
    mayEnter () {
      return (new Date(this.event.startDate) - this.currentTime) <= enterEarlyBuffer
    },
    isOver () {
      return (this.currentTime - new Date(this.event.endDate)) >= enterLateBuffer
    },
    enterOnlineText () {
      if (this.isOver) {
        return this.$i18n('events.meeting.past')
      }
      if (this.mayEnter) {
        return this.$i18n('events.meeting.present')
      }
      return this.$i18n('events.meeting.future')
    },
  },
  async mounted () {
    // Make sure to update the display at the critical times
    const timeUntilStart = new Date(this.event.startDate) - this.currentTime - enterEarlyBuffer
    if (timeUntilStart > 0) {
      await new Promise(resolve => window.setTimeout(resolve, timeUntilStart))
      this.currentTime = new Date()
    }

    const timeUntilEnd = new Date(this.event.endDate) - this.currentTime + enterLateBuffer
    if (timeUntilEnd > 0) {
      await new Promise(resolve => window.setTimeout(resolve, timeUntilStart))
      this.currentTime = new Date()
    }
  },
  methods: {
    joinMeeting () {
      this.showConferencePopup(this.event.regionId)
    },
  },
}
</script>
