<template>
  <BasePage>
    <template #top>
      <Breadcrumbs :items="breadcrumbs" />
      <EventPanel
        :event="event"
        :border="true"
        :may-edit="mayEdit"
        :invite-count="currentAttendees?.inviteCount"
        :answer-count="answerCount"
        :status="inviteStatus"
        class="mb-3"
        @update:status="updateSelfInAttendees"
      />
      <b-alert :show="event.isPublic" variant="info">
        <i class="fas fa-door-open mr-2" />
        {{ $t('events.public_info') }}
      </b-alert>
    </template>

    <template #left>
      <EventLocation :event="event" />
    </template>

    <template v-if="isLoggedIn" #right>
      <EventAttendees :attendees="currentAttendees" />
    </template>

    <Container :title="$t('description')" wrap-content>
      <Markdown :source="event.description" />
    </Container>

    <Wall
      v-if="isLoggedIn"
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

import { useUserStore } from '@/stores/user'
import { EventInvitationResponse } from '@/stores/events'
import Breadcrumbs from '@/views/partials/Navigation/Breadcrumbs.vue'

const userStore = useUserStore()

export default {
  components: { BasePage, Wall, EventPanel, Container, EventLocation, EventAttendees, Markdown, Breadcrumbs },
  props: {
    event: { type: Object, required: true },
    mayEdit: { type: Boolean, default: false },
    attendees: { type: Object, default: () => null },
    inviteStatus: { type: Number, default: 0 },
  },
  data () {
    return {
      currentAttendees: this.attendees,
      oldStatus: this.inviteStatus,
    }
  },
  computed: {
    breadcrumbs () {
      return [
        { href: this.$url('publicRegion', this.event.regionId), text: this.event.regionName },
        { href: this.$url('events', this.event.regionId), text: this.$t('events.bread') },
        { text: this.event.name },
      ]
    },
    answerCount () {
      return this.currentAttendees ? this.currentAttendees.accepted.length + this.currentAttendees.maybe.length + this.currentAttendees.declined : 0
    },
    isLoggedIn () {
      return userStore.isLoggedIn
    },
  },
  async created () {
    document.title += ` | ${this.event.name} (${this.event.regionName})`
  },
  methods: {
    updateSelfInAttendees (newStatus) {
      if (!this.currentAttendees) return
      const self = {
        id: userStore.getUserId,
        name: userStore.getUserFirstName,
        avatar: userStore.getAvatar,
      }
      this.currentAttendees.maybe = this.currentAttendees.maybe.filter(user => user.id !== self.id)
      this.currentAttendees.accepted = this.currentAttendees.accepted.filter(user => user.id !== self.id)
      if (this.oldStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO && newStatus !== EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO) {
        this.currentAttendees.declined--
      } else if (this.oldStatus !== EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO && newStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO) {
        this.currentAttendees.declined++
      }
      if (newStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_YES) {
        this.currentAttendees.accepted.push(self)
      } else if (newStatus === EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE) {
        this.currentAttendees.maybe.push(self)
      }

      this.oldStatus = newStatus
    },
  },
}
</script>
