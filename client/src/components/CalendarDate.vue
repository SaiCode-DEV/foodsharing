<template>
  <div class="datebox corner-all" :class="classes">
    <div class="px-1 month">
      {{ displayedMonthAndYear }}
    </div>
    <div
      v-if="!isEventToday && !isEventTomorrow"
      class="px-1 day"
    >
      {{ displayedDay }}
    </div>
    <div
      v-else-if="isEventToday"
      class="px-1 day"
      style="font-size: 100%"
    >
      {{ today }}
    </div>
    <div
      v-else-if="isEventTomorrow"
      class="px-1 day"
      style="font-size: 100%"
    >
      {{ tomorrow }}
    </div>
  </div>
</template>

<script>
export default {
  props: {
    dateObject: { type: Date, required: true },
    classes: { type: String, default: '' },
  },
  computed: {
    displayedDay () {
      return this.$dateFormatter.format(this.dateObject, {
        day: 'numeric',
        weekday: 'short',
      })
    },
    displayedMonthAndYear () {
      return this.$dateFormatter.format(this.dateObject, {
        month: 'long',
        year: 'numeric',
      })
    },
    isEventToday () {
      return this.$dateFormatter.isToday(this.dateObject)
    },
    isEventTomorrow () {
      return this.$dateFormatter.isTomorrow(this.dateObject)
    },
    today () {
      return this.$t('date.Today')
    },
    tomorrow () {
      return this.$t('date.-- Tomorrow')
    },
  },
}
</script>

<style lang="scss" scoped>
.datebox {
  --calendar-highlight-bg: var(--fs-color-warning-500); // new orange
  --calendar-highlight-text: var(--fs-color-secondary-500); // modified kale
  --calendar-font-size: 1rem;
  --calendar-line-height: 1.2;
  --calendar-border-radius: 6px;

  text-align: center;

  .month {
    min-width: calc(5 * var(--calendar-font-size));
    border-top-left-radius: var(--calendar-border-radius);
    border-top-right-radius: var(--calendar-border-radius);
    font-size: var(--calendar-font-size);
    letter-spacing: -.5px;
    line-height: var(--calendar-line-height);
    font-weight: bold;
    background-color: var(--calendar-highlight-bg);
    color: var(--fs-color-light);
    text-shadow: 1px 1px 1px var(--fs-color-gray-alpha-80);
  }

  .day {
    border: 2px solid var(--fs-border-default);
    border-radius: var(--calendar-border-radius);
    border-top: 0;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
    color: var(--calendar-highlight-text);
    font-family: var(--fs-font-family-headline);
    font-size: calc(1.57 * var(--calendar-font-size));
    line-height: var(--calendar-line-height);

    // letter-spacing has alignment problems
    &::first-letter {
      margin-right: calc(0.1 * var(--calendar-font-size));
    }
  }
}
</style>
