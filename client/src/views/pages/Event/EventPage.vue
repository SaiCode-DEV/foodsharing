<template>
  <BasePage>
    <template #top>
      <EventPanel
        :event="event"
        :border="true"
        :may-edit="mayEdit"
        :invite-count="currentAttendees.inviteCount"
        :region-name="regionName"
        :status="inviteStatus"
        @update:status="updateSelfInAttendees"
      />
    </template>

    <template #left>
      <EventLocation :event="event" />
    </template>

    <template #right>
      <EventAttendees :attendees="currentAttendees" />
    </template>

    <Container :title="'Beschreibung'" wrap-content>
      <Markdown :source="event.description" />
    </Container>

    <Wall
      target="event"
      :target-id="event.id"
    />
  </BasePage>
</template>
<script>
import BasePage from '@/views/pages/Layout/BasePage.vue'
import Wall from '@/components/Wall/Wall.vue'
import EventPanel from '@php/Modules/Event/components/EventPanel.vue' // TODO move
import Container from '@/components/Container/Container.vue'
import EventLocation from '@/components/Event/EventLocation.vue'
import EventAttendees from '@/components/Event/EventAttendees.vue'
import Markdown from '@/components/Markdown/Markdown.vue'

import { getters as regionGetters } from '@/stores/regions'
import { getters as userGetters } from '@/stores/user'
import { EventInvitationResponse } from '@/stores/events'

export default {
  components: { BasePage, Wall, EventPanel, Container, EventLocation, EventAttendees, Markdown },
  props: {
    event: { type: Object, required: true },
    mayEdit: { type: Boolean, default: false },
    attendees: { type: Object, required: true },
    inviteStatus: { type: Number, default: 0 },
  },
  data () {
    return {
      currentAttendees: this.attendees,
    }
  },
  computed: {
    regionName () {
      return regionGetters.find(this.event.regionId)?.name
    },
  },
  methods: {
    updateSelfInAttendees (newStatus) {
      const self = {
        id: userGetters.getUser().id,
        name: userGetters.getUser().firstname,
        avatar: userGetters.getUser().avatar,
      }
      this.currentAttendees.maybe = this.currentAttendees.maybe.filter(user => user.id !== self.id)
      this.currentAttendees.accepted = this.currentAttendees.accepted.filter(user => user.id !== self.id)
      if (newStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_YES) {
        this.currentAttendees.accepted.push(self)
      } else if (newStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE) {
        this.currentAttendees.maybe.push(self)
      }
    },
  },
}
</script>
