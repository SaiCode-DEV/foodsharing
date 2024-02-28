<template>
  <a
    class="list-group-item list-group-item-action"
    :href="$url('event', entry.id)"
  >
    <div class="d-flex">
      <div
        v-b-tooltip.hover="dateTooltip"
        class="event-item-date flex-column mr-2 text-center rounded default"
        :class="{'accept': status === 1, 'maybe': status === 2}"
      >
        <small class="font-weight-bold" v-html="displayedMonth" />
        <div class="event-item-date-container d-flex flex-column bg-white justify-content-center text-dark">
          <span
            v-if="isEventToday"
            v-html="$i18n('date.Today')"
          />
          <span
            v-else-if="isEventTomorrow"
            class="small"
            v-html="$i18n('date.-- Tomorrow')"
          />
          <span
            v-else-if="$dateFormatter.getDifferenceToNowInDays(startDate) < 3"
            v-html="displayedDay"
          />
          <span
            v-else
            class="small"
            v-html="displayedBothDay"
          />
        </div>
      </div>
      <div class="d-flex justify-content-between flex-column truncated">
        <div>
          <h6
            v-b-tooltip.hover="entry.name.length > 30 ? entry.name : null"
            class="field-headline m-0 text-truncate"
            v-html="entry.name"
          />
          <span
            :href="$url('forum', entry.region_id)"
            class="d-block small text-muted text-truncate"
            v-html="entry.regionName"
          />
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted mt-auto">
            <i class="fas fa-clock" />
            <span
              v-html="$i18n('events.span', { from: displayedStart, until: displayedEnd })"
            />
          </div>
          <a
            v-if="!options"
            class="d-none d-sm-block small"
            :href="$url('event', entry.id)"
          >
            <span>
              {{ $i18n('events.button.change') }} ({{ $i18n('events.button.' + ['yes', 'maybe', 'no'][status - 1]) }})
            </span>
          </a>
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
        {{ $i18n('events.button.yes') }}
      </button>
      <button
        class="list-group-item list-row-item list-group-item-action"
        :class="{'maybe': status === EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE}"
        @click.prevent="sendInvitationUpdate(EventInvitationResponse.EVENT_INVITATION_RESPONSE_MAYBE)"
      >
        <i class="fas fa-question-circle d-none d-sm-inline" />
        {{ $i18n('events.button.maybe') }}
      </button>
      <button
        class="list-group-item list-row-item list-group-item-action"
        :class="{'default': status === EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO}"
        @click.prevent="sendInvitationUpdate(EventInvitationResponse.EVENT_INVITATION_RESPONSE_NO)"
      >
        <i class="fas fa-fw fa-calendar-times d-none d-sm-inline" />
        {{ $i18n('events.button.no') }}
      </button>
    </div>
  </a>
</template>

<script>
import { EventInvitationResponse, mutations } from '@/stores/events'
import { showLoader, hideLoader, pulseSuccess, pulseError } from '@/script'

export default {
  props: {
    entry: { type: Object, default: () => {} },
    options: { type: Boolean, default: false },
  },
  data () {
    return {
      EventInvitationResponse: EventInvitationResponse,
      startDate: new Date(this.entry.start_ts * 1000),
      endDate: new Date(this.entry.end_ts * 1000),
      status: this.entry.status,
    }
  },
  computed: {
    displayedDay () {
      return this.$dateFormatter.format(this.startDate, {
        weekday: 'short',
      })
    },
    displayedBothDay () {
      return this.$dateFormatter.format(this.startDate, {
        day: 'numeric',
        weekday: 'short',
      })
    },
    displayedMonth () {
      return this.$dateFormatter.format(this.startDate, {
        month: 'long',
      })
    },
    isEventToday () {
      return this.$dateFormatter.isToday(this.startDate)
    },
    isEventTomorrow () {
      return this.$dateFormatter.isTomorrow(this.startDate)
    },
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
    async sendInvitationUpdate (newStatus) {
      showLoader()
      try {
        await mutations.setInvitationResponse(this.entry.id, newStatus)
        const texts = ['events.rsvp.yes', 'events.rsvp.maybe', 'events.rsvp.no']
        pulseSuccess(this.$i18n(texts[newStatus - 1]))
        this.status = newStatus
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
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
  color: var(--fs-color-dark);
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

</style>
