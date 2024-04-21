<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <label>{{ $i18n('register.geb_datum') }}<sup><i class="fas fa-asterisk" /></sup></label>
    </div>
    <div class="mt-2 col-sm-auto">
      <b-form-datepicker
        id="register-datepicker"
        v-model="dateString"
        v-bind="trans || {}"
        :state="isValid"
        :show-decade-nav="showDecadeNav"
        :start-weekday="weekday"
        :date-format-options="{ year: 'numeric', month: '2-digit', day: '2-digit' }"
        :min="minDate"
        :max="maxDate"
        :label-reset-button="$i18n('globals.reset')"
        :label-close-button="$i18n('globals.close')"
        reset-button
        close-button
      />
      <div
        v-if="!isValid"
        class="alert alert-danger mt-2"
        v-text="$i18n('register.error_birthdate')"
      />
    </div>
    <div class="mt-3 col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" />
        <span v-text="$i18n('register.birthdate_hint')" />
      </div>
    </div>
    <button
      class="btn btn-primary ml-3 mt-3"
      type="button"
      @click="$emit('prev')"
    >
      {{ $i18n('register.prev') }}
    </button>
    <button
      class="btn btn-primary mt-3"
      type="submit"
      :disabled="!isValid"
      @click.prevent="redirect()"
    >
      {{ $i18n('register.next') }}
    </button>
    <span class="mr-3 d-flex flex-row-reverse">{{ $i18n('register.requiredFields') }}<sup><i class="fas fa-asterisk" /></sup></span>
  </form>
</template>

<script>
export default {
  props: {
    birthdate: {
      type: Date,
      default: null,
    },
  },
  data () {
    const date = new Date()
    return {
      dateString: this.birthdate,
      showDecadeNav: true,
      local: 'de',
      minDate: new Date(date.getFullYear() - 125, date.getMonth(), date.getDate()),
      maxDate: new Date(date.getFullYear() - 18, date.getMonth(), date.getDate()),
      weekday: 1,
      trans: {
        labelPrevDecade: this.$i18n('bootstrap-datepicker.labelPrevDecade'),
        labelPrevYear: this.$i18n('bootstrap-datepicker.labelPrevYear'),
        labelPrevMonth: this.$i18n('bootstrap-datepicker.labelPrevMonth'),
        labelCurrentMonth: this.$i18n('bootstrap-datepicker.labelCurrentMonth'),
        labelNextMonth: this.$i18n('bootstrap-datepicker.labelNextMonth'),
        labelNextYear: this.$i18n('bootstrap-datepicker.labelNextYear'),
        labelNextDecade: this.$i18n('bootstrap-datepicker.labelNextDecade'),
        labelToday: this.$i18n('bootstrap-datepicker.labelToday'),
        labelSelected: this.$i18n('bootstrap-datepicker.labelSelected'),
        labelNoDateSelected: this.$i18n('bootstrap-datepicker.labelNoDateSelected'),
        labelCalendar: this.$i18n('bootstrap-datepicker.labelCalendar'),
        labelNav: this.$i18n('bootstrap-datepicker.labelNav'),
        labelHelp: this.$i18n('bootstrap-datepicker.labelHelp'),
      },
    }
  },
  computed: {
    date () {
      return new Date(this.dateString)
    },
    isValid () {
      const age = this.$dateFormatter.getDifferenceToNowInYears(this.date)
      return age >= 18 && age <= 125 && !!this.dateString
    },
  },
  methods: {
    redirect () {
      if (this.isValid) {
        this.$emit('save', this.date)
        this.$emit('next')
      }
    },
  },
}
</script>
