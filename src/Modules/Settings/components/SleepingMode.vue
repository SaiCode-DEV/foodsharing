<template>
  <div>
    <div
      class="alert alert-secondary"
      role="alert"
    >
      {{ $i18n('settings.sleep.info') }}
    </div>

    <label>{{ $i18n('settings.sleep.status') }}</label>
    <b-form-select
      v-model="currentSleepStatus"
      :options="sleepingOptions"
    />

    <div
      v-if="currentSleepStatus === SLEEP_STATUS.TEMP"
      class="pt-4"
    >
      <label>{{ $i18n('settings.sleep.range') }}</label>
      <b-row>
        <b-col
          cols="12"
          lg="6"
        >
          <label>{{ $i18n('settings.sleep.from') }}</label>
          <b-form-datepicker
            v-model="currentSleepFrom"
            :min="new Date()"
            v-bind="labelsCalendar || {}"
            :locale="locale"
            :state="isSleepDateValid(currentSleepFrom)"
            class="mb-2"
          />
        </b-col>
        <b-col
          cols="12"
          lg="6"
        >
          <label>{{ $i18n('settings.sleep.until') }}</label>
          <b-form-datepicker
            v-model="currentSleepUntil"
            v-bind="labelsCalendar || {}"
            :min="new Date(currentSleepFrom) > new Date() ? new Date(currentSleepFrom) : new Date()"
            :locale="locale"
            :state="isSleepDateValid(currentSleepUntil)"
            class="mb-2"
          />
        </b-col>
      </b-row>
    </div>

    <div
      v-if="currentSleepStatus > SLEEP_STATUS.NONE"
      class="pt-4"
    >
      <label>{{ $i18n('settings.sleep.message') }}</label>
      <b-form-textarea
        id="textarea"
        v-model="currentSleepMessage"
        rows="3"
        max-rows="6"
      />
    </div>

    <div class="pt-4">
      <div
        class="alert alert-warning"
        role="alert"
      >
        {{ $i18n('settings.sleep.show') }}
      </div>
    </div>

    <b-button
      :disabled="!sendButtonIsValid()"
      variant="primary"
      @click="trySetSleepStatus"
    >
      {{ $i18n('globals.save') }}
    </b-button>
  </div>
</template>

<script>
import { setSleepStatus } from '@/api/user'
import i18n, { locale } from '@/helper/i18n'
import { pulseError, pulseSuccess } from '@/script'
import { SLEEP_STATUS } from '@/stores/user'

export default {
  props: {
    sleepStatus: { type: Number, required: true },
    sleepFrom: { type: String, default: null },
    sleepUntil: { type: String, default: null },
    sleepMessage: { type: String, default: '' },
  },

  data () {
    return {
      locale: locale,
      labelsCalendar: {
        labelPrevYear: i18n('calendar.labelPrevYear'),
        labelPrevMonth: i18n('calendar.labelPrevMonth'),
        labelCurrentMonth: i18n('calendar.labelCurrentMonth'),
        labelNextMonth: i18n('calendar.labelNextMonth'),
        labelNextYear: i18n('calendar.labelNextYear'),
        labelToday: i18n('calendar.labelToday'),
        labelSelected: i18n('calendar.labelSelected'),
        labelNoDateSelected: i18n('calendar.labelNoDateSelected'),
        labelCalendar: i18n('calendar.labelCalendar'),
        labelNav: i18n('calendar.labelNav'),
        labelHelp: i18n('calendar.labelHelp'),
        labelTodayButton: i18n('calendar.labelToday'),
      },
      sleepingOptions: [
        { value: SLEEP_STATUS.NONE, text: this.$i18n('settings.sleep.none') },
        { value: SLEEP_STATUS.TEMP, text: this.$i18n('settings.sleep.temp') },
        { value: SLEEP_STATUS.FULL, text: this.$i18n('settings.sleep.full') },
      ],
      currentSleepStatus: this.sleepStatus,
      currentSleepFrom: this.sleepFrom,
      currentSleepUntil: this.sleepUntil,
      currentSleepMessage: this.sleepMessage,
      startDate: new Date(),
    }
  },
  computed: {
    SLEEP_STATUS () {
      return SLEEP_STATUS
    },
  },
  methods: {
    isSleepDateValid (date) {
      return date !== null && !isNaN(Date.parse(date))
    },
    isSleepStatusValid () {
      return this.currentSleepStatus >= 0 && this.currentSleepStatus <= 2
    },
    sendButtonIsValid () {
      switch (this.currentSleepStatus) {
        case SLEEP_STATUS.NONE:
          return this.isSleepStatusValid()
        case SLEEP_STATUS.TEMP:
          return this.isSleepStatusValid && this.isSleepDateValid(this.currentSleepFrom) && this.isSleepDateValid(this.currentSleepUntil)
        case SLEEP_STATUS.FULL:
          return this.isSleepDateValid(this.startDate) && this.isSleepStatusValid
        default:
          return false
      }
    },
    async trySetSleepStatus () {
      const sendingData = {
        status: null,
        from: null,
        until: null,
        message: null,
      }

      switch (this.currentSleepStatus) {
        case SLEEP_STATUS.NONE:
          sendingData.status = this.currentSleepStatus
          break
        case SLEEP_STATUS.TEMP:
          sendingData.status = this.currentSleepStatus
          sendingData.from = this.currentSleepFrom
          sendingData.until = this.currentSleepUntil
          sendingData.message = this.currentSleepMessage
          break
        case SLEEP_STATUS.FULL:
          sendingData.status = this.currentSleepStatus
          sendingData.from = this.startDate
          sendingData.message = this.currentSleepMessage
          break
        default:
          return false
      }

      try {
        await setSleepStatus(sendingData.status, sendingData.from, sendingData.until, sendingData.message)
        pulseSuccess(i18n('success'))
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
    },
  },
}
</script>
