<template>
  <a
    class="list-group-item list-group-item-action"
    :href="$url('poll', entry.id)"
  >
    <div class="d-flex">
      <div
        v-b-tooltip.hover="dateTooltip"
        class="event-item-date flex-column mr-2 text-center rounded default"
        :class="{
          soon: $dateFormatter.getDifferenceToNowInDays(endDate) < 2,
          'in-future': inFuture,
        }"
      >
        <span class="font-weight-bold" v-text="displayedMonth" />
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
            v-else-if="$dateFormatter.getDifferenceToNowInDays(endDate) < 3"
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
          >
            {{ entry.name }}
            <b-badge
              v-if="inFuture"
              pill
              variant="warning"
            >
              {{ $t('poll.in_future') }}
            </b-badge>
          </h6>
          <span
            class="d-block small text-muted text-truncate"
            v-text="entry.regionName"
          />
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted mt-auto">
            <i class="fas fa-clock" />
            <span
              v-text="$t('polls.until', { until: displayedEnd })"
            />
          </div>
        </div>
      </div>
    </div>
  </a>
</template>

<script>

export default {
  props: {
    entry: { type: Object, default: () => {} },
  },
  data () {
    return {
      startDate: new Date(this.entry.startDate),
      endDate: new Date(this.entry.endDate),
      status: this.entry.status,
      inFuture: this.entry.inFuture,
    }
  },
  computed: {
    displayedBothDay () {
      return this.$dateFormatter.format(this.endDate, {
        day: 'numeric',
        weekday: 'short',
      })
    },
    isEventToday () {
      return this.$dateFormatter.isToday(this.endDate)
    },
    isEventTomorrow () {
      return this.$dateFormatter.isTomorrow(this.endDate)
    },
    dateTooltip () {
      return `${this.$dateFormatter.dateTime(this.endDate)} (${this.$dateFormatter.relativeTime(this.endDate)}`
    },
    displayedDay () {
      return this.$dateFormatter.format(this.endDate, {
        weekday: 'short',
      })
    },
    displayedMonth () {
      return this.$dateFormatter.format(this.endDate, {
        month: 'long',
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

  &.default {
    z-index: 1;
    color: var(--fs-color-light);
    background-color: var(--fs-color-secondary-500);
    border: 1px solid var(--fs-color-secondary-500);
  }

  &.soon,
  &.soon:focus {
    z-index: 3;
    background-color: var(--fs-color-danger-500);
    border-color: var(--fs-color-danger-500);
  }

  &.in-future,
  &.in-future:focus {
    z-index: 3;
    background-color: var(--fs-color-gray-500);
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

.truncated {
  flex: 1;

  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

</style>
