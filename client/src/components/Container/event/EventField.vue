<template>
  <FsLink
    :to="$url('event', entry.id)"
    class="list-group-item list-group-item-action"
  >
    <div class="d-flex">
      <div
        v-b-tooltip.hover="dateTooltip"
        class="event-item-date flex-column mr-2 text-center rounded default"
        :class="{'accept': status === 1, 'maybe': status === 2}"
      >
        <small class="font-weight-bold" v-text="displayedMonth" />
        <div class="event-item-date-container d-flex flex-column bg-white justify-content-center text-dark">
          <span
            v-if="isEventToday"
            v-text="$t('date.Today')"
          />
          <span
            v-else-if="isEventTomorrow"
            class="small"
            v-text="$t('date.-- Tomorrow')"
          />
          <span
            v-else-if="$dateFormatter.getDifferenceToNowInDays(startDate) < 3"
            v-text="displayedDay"
          />
          <span
            v-else
            class="small"
            v-text="displayedBothDay"
          />
        </div>
      </div>
      <div class="d-flex justify-content-between flex-column truncated">
        <div>
          <h6
            v-b-tooltip.hover="entry.name.length > 30 ? entry.name : null"
            class="field-headline m-0 text-truncate"
            v-text="entry.name"
          />
          <span
            class="d-block small text-muted text-truncate"
            v-text="entry.regionName"
          />
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted mt-auto">
            <i class="fas fa-clock" />
            <span
              v-text="$t('events.span', { from: displayedStart, until: displayedEnd })"
            />
            <span
              v-if="viewerTimeHint"
              v-text="$t('date.local_time')"
            />
            <span
              v-if="viewerTimeHint"
              class="viewer-time-hint small"
              v-text="viewerTimeHint"
            />
          </div>
          <FsLink
            v-if="!options"
            :to="$url('event', entry.id)"
            class="d-none d-sm-block small"
            @click.native.stop
          >
            <span>
              {{ $t('events.button.change') }} ({{ $t('events.button.' + ['yes', 'maybe', 'no'][status - 1]) }})
            </span>
          </FsLink>
        </div>
      </div>
    </div>
    <div
      v-if="options"
      class="list-group list-group-horizontal mt-2 small text-center"
    >
      <button
        class="list-group-item list-row-item list-group-item-action"
        :class="{'accept': status === EventInvitationResponse.EVENT_INVITATION_RESPONSE_YES}"
        @click.prevent="sendInvitationUpdate(EventInvitationResponse.EVENT_INVITATION_RESPONSE_YES)"
      >
        <i class="fas fa-calendar-check d-none d-sm-inline" />
        {{ $t('events.button.yes') }}
      </button>
      <button
        class="list-group-item list-row-item list-group-item-action"
        :class="{'maybe': status === EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE}"
        @click.prevent="sendInvitationUpdate(EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE)"
      >
        <i class="fas fa-question-circle d-none d-sm-inline" />
        {{ $t('events.button.maybe') }}
      </button>
      <button
        class="list-group-item list-row-item list-group-item-action"
        :class="{'default': status === EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO}"
        @click.prevent="sendInvitationUpdate(EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO)"
      >
        <i class="fas fa-fw fa-calendar-times d-none d-sm-inline" />
        {{ $t('events.button.no') }}
      </button>
    </div>
  </FsLink>
</template>

<script>
import { EventInvitationResponse, mutations } from '@/stores/events'
import { parseWallClock, DEFAULT_TIME_ZONE } from '@/helper/date-formatter'
import { showLoader, hideLoader, pulseSuccess, pulseError } from '@/script'
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  props: {
    entry: { type: Object, default: () => {} },
    options: { type: Boolean, default: false },
  },
  data () {
    return {
      EventInvitationResponse,
      // start/end are naive German wall-clock strings; parse them as such so the
      // instant (and with it relative times) stays correct in every browser timezone.
      startDate: parseWallClock(this.entry.start),
      endDate: parseWallClock(this.entry.end),
      status: this.entry.status,
    }
  },
  computed: {
    // Events are bound to a place, so their times render in the platform timezone
    // (the event's own region timezone can replace this later, #2758). History
    // timestamps elsewhere stay in the viewer's timezone.
    timeZone () {
      return DEFAULT_TIME_ZONE
    },
    displayedDay () {
      return this.$dateFormatter.format(this.startDate, {
        weekday: 'short',
        timeZone: this.timeZone,
      })
    },
    displayedBothDay () {
      return this.$dateFormatter.format(this.startDate, {
        day: 'numeric',
        weekday: 'short',
        timeZone: this.timeZone,
      })
    },
    displayedMonth () {
      return this.$dateFormatter.format(this.startDate, {
        month: 'long',
        timeZone: this.timeZone,
      })
    },
    isEventToday () {
      return this.$dateFormatter.isToday(this.startDate, { timeZone: this.timeZone })
    },
    isEventTomorrow () {
      return this.$dateFormatter.isTomorrow(this.startDate, { timeZone: this.timeZone })
    },
    dateTooltip () {
      return `${this.$dateFormatter.dateTime(this.startDate, { timeZone: this.timeZone })} (${this.$dateFormatter.relativeTime(this.startDate)})`
    },
    viewerTimeHint () {
      // shown only for viewers whose own timezone reads a different time (#2762)
      const viewerStart = this.$dateFormatter.viewerTime(this.startDate, { timeZone: this.timeZone })
      if (!viewerStart) {
        return null
      }
      if (!this.entry.end) {
        return this.$t('date.your_time', { time: viewerStart })
      }
      // mirror the displayed span: time only on the same viewer-local day, date + time otherwise
      const viewerEnd = this.$dateFormatter.isSame(this.endDate, this.startDate, {})
        ? this.$dateFormatter.time(this.endDate)
        : this.$dateFormatter.format(this.endDate, {
          day: 'numeric',
          month: 'numeric',
          hour: 'numeric',
          minute: 'numeric',
        })
      return this.$t('date.your_time', { time: this.$t('events.span', { from: viewerStart, until: viewerEnd }) })
    },
    displayedStart () {
      return this.$dateFormatter.time(this.startDate, { timeZone: this.timeZone })
    },
    displayedEnd () {
      if (this.$dateFormatter.isSame(this.endDate, this.startDate, { timeZone: this.timeZone })) {
        return this.$dateFormatter.time(this.endDate, { timeZone: this.timeZone })
      } else {
        return this.$dateFormatter.format(this.endDate, {
          day: 'numeric',
          month: 'numeric',
          hour: 'numeric',
          minute: 'numeric',
          timeZone: this.timeZone,
        })
      }
    },
  },
  methods: {
    async sendInvitationUpdate (newStatus) {
      showLoader()
      try {
        await mutations.setInvitationResponse(this.entry.id, newStatus)
        const texts = ['events.rsvp.yes', 'events.rsvp.maybe', 'events.rsvp.no']
        pulseSuccess(this.$t(texts[newStatus - 1]))
        this.status = newStatus
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
.event-item-date-container {
  border-radius: 0 0 var(--border-radius) var(--border-radius);
  min-height: 3rem;
  font-size: 1.15rem;
  font-family: var(--fs-font-family-headline);
}

.event-item-date {
  display: flex;
  width: 5rem;

  @media (max-width: 320px) {
    display: none;
  }

  &.accept {
    border-color: var(--fs-color-secondary-500);
  }

  &.maybe {
    border-color: var(--fs-color-warning-500);
  }

  &.decline  {
    border-color: var(--fs-color-gray-500);
  }
}

.small .list-group-item {
  padding: 0.5rem 0;

  &:first-child {
    border-right-width: 0;
  }

  &:not(:first-child):not(:last-child) {
    border-left-width: 0;
    border-right-width: 0;
  }
}

.default {
  z-index: 1;
  color: var(--fs-color-light);
  background-color: var(--fs-color-gray-500);
  border: 1px solid var(--fs-color-gray-500);
}

.accept,
.accept:focus {
  z-index: 2;
  color: var(--fs-color-light);
  background-color: var(--fs-color-secondary-500);
}

.maybe,
.maybe:focus {
  z-index: 3;
  color: var(--fs-color-light);
  background-color: var(--fs-color-warning-500);
}

.decline,
.decline:focus {
  z-index: 1;
  background-color: var(--fs-color-gray-500);
}

.truncated {
  flex: 1;

  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.viewer-time-hint {
  display: block;
}

</style>
