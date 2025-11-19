<template>
  <b-form
    class="date-range-picker"
    inline
  >
    <DatePicker
      v-b-tooltip.noninteractive="$t('date.from')"
      :value="fromDate"
      class="date-picker-from"
      :min="minFromDate"
      :max="toDate || maxToDate"
      :short="short"
      @input="(value) => $emit('update:fromDate', value)"
    />
    <hr class="date-separator">
    <DatePicker
      v-b-tooltip.noninteractive="$t('date.to')"
      :value="toDate"
      class="date-picker-to"
      :min="fromDate || minFromDate"
      :max="maxToDate"
      :short="short"
      @input="(value) => $emit('update:toDate', value)"
    />
  </b-form>
</template>
<script>
import DatePicker from './DatePicker.vue'

export default {
  components: { DatePicker },
  props: {
    fromDate: { type: Date, default: null },
    toDate: { type: Date, default: null },
    minFromDate: { type: Date, default: null },
    maxToDate: { type: Date, default: null },
    short: { type: Boolean, default: false },
  },
}
</script>

<style lang="scss" scoped>
.date-range-picker::v-deep .b-form-datepicker {
  flex: 1 0 0;
}

.date-separator {
  width: 1rem;
  border-top-color: var(--fs-border-default);
}

::v-deep label.form-control {
  flex-grow: 1;
}
</style>
