<!-- eslint-disable vue/max-attributes-per-line -->
<template>
  <div class="event-panel bootstrap margin-bottom">
    <b-card :class="{ border }">
      <b-media no-body class="d-flex w-100">
        <b-media-aside
          v-b-tooltip.hover="dateTooltip"
          class="mr-2 flex-column"
        >
          <CalendarDate :date-object="startDate" />
        </b-media-aside>

        <b-media-body class="ml-1 w-100">
          <a :href="$url('event', eventId)" class="event-link">
            <h6 class="my-0 mr-1">
              {{ title }}
              <b-button
                v-if="mayEdit"
                v-b-tooltip="$i18n('events.edit')"
                :href="$url('eventEdit', eventId)"
                size="sm"
                variant="outline-secondary ml-2"
              >
                <i class="fas fa-fw fa-pencil-alt" />
              </b-button>
            </h6>
          </a>
          <div v-if="regionName" class="flex-md-shrink-0">
            <a :href="$url('events', regionId)">{{ regionName }}</a>
            ({{ $i18n('events.invitedCount', { count: inviteCount }) }})
          </div>
          <div
            class="my-1 d-inline-block event-date"
          >
            <i class="far fa-fw fa-clock" />
            {{ $i18n('events.span', { from: displayedStart, until: displayedEnd }) }}
          </div>
          <br>

          <b-button-group v-if="statusAvailable()" size="sm">
            <b-button
              :variant="statusVariant(EventInvitationStatus.EVENT_INVITATION_RESPONSE_YES)"
              @click="sendInvitationUpdate(EventInvitationStatus.EVENT_INVITATION_RESPONSE_YES)"
            >
              <i class="fas fa-fw fa-calendar-check" />
              {{ $i18n('events.button.yes') }}
            </b-button>
            <b-button
              :variant="statusVariant(EventInvitationStatus.EVENT_INVITATION_RESPONSE_MAYBE)"
              @click="sendInvitationUpdate(EventInvitationStatus.EVENT_INVITATION_RESPONSE_MAYBE)"
            >
              <i class="fas fa-fw fa-question-circle" />
              <span class="d-none d-sm-inline">
                {{ $i18n('events.button.maybe') }}
              </span>
            </b-button>
            <b-button
              :variant="statusVariant(EventInvitationStatus.EVENT_INVITATION_RESPONSE_NO)"
              @click="sendInvitationUpdate(EventInvitationStatus.EVENT_INVITATION_RESPONSE_NO)"
            >
              <!-- TODO faded UI after clicking (don't remove, to allow correcting mis-clicks) -->
              <i class="fas fa-fw fa-calendar-times" />
              <span class="d-none d-sm-inline">
                {{ $i18n('events.button.no') }}
              </span>
            </b-button>
          </b-button-group>
        </b-media-body>
      </b-media>
    </b-card>
  </div>
</template>

<script>
import CalendarDate from '@/components/CalendarDate'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import { mutations, EventInvitationResponse } from '@/stores/events'

export default {
  components: { CalendarDate },
  props: {
    eventId: { type: Number, required: true },
    regionName: { type: String, default: '' },
    regionId: { type: Number, default: 0 },
    inviteCount: { type: Number, default: 0 },
    start: { type: String, required: true },
    end: { type: String, required: true },
    title: { type: String, default: '' },
    mayEdit: { type: Boolean, default: false },
    status: { type: Number, default: 0 },
    border: { type: Boolean, default: false },
  },
  data () {
    return {
      EventInvitationStatus: EventInvitationResponse,
      startDate: new Date(Date.parse(this.start)),
      endDate: new Date(Date.parse(this.end)),
      currentStatus: this.status,
    }
  },
  computed: {
    dateTooltip () {
      return `${this.$dateFormatter.dateTime(this.startDate)} (${this.$dateFormatter.relativeTime(this.startDate)}`
    },
    displayedStart () {
      return this.$dateFormatter.format(this.startDate, {
        hour: 'numeric',
        minute: 'numeric',
      })
    },
    displayedEnd () {
      if (this.$dateFormatter.isSame(this.endDate, this.startDate)) {
        return this.$dateFormatter.format(this.endDate, {
          hour: 'numeric',
          minute: 'numeric',
        })
      } else {
        return this.$dateFormatter.format(this.endDate, {
          day: 'numeric',
          month: 'numeric',
          hour: 'numeric',
          minute: 'numeric',
        })
      }
    },
  },
  methods: {
    statusAvailable: function () {
      return this.currentStatus >= 0
    },
    statusVariant: function (s) {
      if (s === this.currentStatus) {
        return 'secondary'
      } else {
        return 'outline-primary'
      }
    },
    edit: function () {},
    async sendInvitationUpdate (newStatus) {
      showLoader()
      try {
        await mutations.setInvitationResponse(this.eventId, newStatus)
        const texts = ['events.rsvp.yes', 'events.rsvp.maybe', 'events.rsvp.no']
        pulseSuccess(this.$i18n(texts[newStatus - 1]))
        this.currentStatus = newStatus
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
// pure-grid is doing very weird things on the dashboard without this:
.event-panel div.btn-group > .btn {
  white-space: initial;
}

.event-link {
  color: initial;
}

.event-date {
  font-size: 0.8rem;
}
</style>
