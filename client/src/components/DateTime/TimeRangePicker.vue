<template>
  <b-form
    class="time-range-picker"
    inline
  >
    <TimePicker
      v-b-tooltip.noninteractive="$t('timepicker.from')"
      :value="fromTime"
      class="time-picker-from"
      :state="state"
      @input="(value) => $emit('update:fromTime', value)"
    />
    <hr class="time-separator" :class="{ invisible: independent, error: state === false }">
    <TimePicker
      v-b-tooltip.noninteractive="$t('timepicker.to')"
      :value="toTime"
      class="time-picker-to"
      :state="state"
      @input="(value) => $emit('update:toTime', value)"
    />
  </b-form>
</template>
<script>
import TimePicker from './TimePicker.vue'

export default {
  components: { TimePicker },
  props: {
    fromTime: { type: String, default: null },
    toTime: { type: String, default: null },
    independent: { type: Boolean, default: false },
  },
  computed: {
    state () {
      if (this.independent || !this.fromTime || !this.toTime) return null
      const [from, to] = [this.fromTime, this.toTime].map(time => time.split(':').map(Number))
      if (from[0] > to[0]) return false
      if (from[0] === to[0] && from[1] > to[1]) return false
      return null
    },
  },
}
</script>

<style lang="scss" scoped>
.time-range-picker::v-deep .time-picker {
  flex: 1 0 0;
}

.time-separator {
  width: 1rem;
  border-top-color: var(--fs-border-default);
  &.error {
    border-color: #cf3a00;
  }
}

::v-deep label.form-control {
  flex-grow: 1;
}
</style>
