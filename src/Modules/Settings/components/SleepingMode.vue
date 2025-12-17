<template>
  <div>
    <div
      class="alert alert-secondary"
      role="alert"
    >
      {{ $t('settings.sleep.info') }}
    </div>

    <label>{{ $t('settings.sleep.status') }}</label>
    <b-form-select
      v-model="currentSleepStatus"
      :options="sleepingOptions"
    />

    <div
      v-if="currentSleepStatus === SLEEP_STATUS.TEMP"
      class="pt-4"
    >
      <label>{{ $t('settings.sleep.range') }}</label>
      <b-row>
        <b-col
          cols="12"
          lg="6"
        >
          <label>{{ $t('settings.sleep.from') }}</label>
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
          <label>{{ $t('settings.sleep.until') }}</label>
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
      <b-form-group
        :description="$t('settings.sleep.message')"
        :label="$t('settings.sleep.message')"
        label-for="textarea"
        class="my-3"
      >
        <b-form-textarea
          id="textarea"
          v-model="currentSleepMessage"
          rows="3"
          max-rows="6"
          :maxlength="maxlengthSleepingMessage"
        />
        <span>{{ $t('storeview.public_info.available_count') }}: {{ maxlengthSleepingMessage - (currentSleepMessage ? currentSleepMessage.length : 0) }}</span>
      </b-form-group>
    </div>
    <div class="pt-4">
      <div
        class="alert alert-warning"
        role="alert"
      >
        {{ $t('settings.sleep.show') }}
      </div>
    </div>

    <b-button
      :disabled="!sendButtonIsValid() || isLoading"
      variant="primary"
      @click="trySetSleepStatus"
    >
      {{ $t('globals.save') }}
    </b-button>
  </div>
</template>

<script setup>
import { defineProps, ref } from 'vue'
import { setSleepStatus } from '@/api/user'
import i18n, { locale } from '@/helper/i18n'
import { pulseError, pulseSuccess } from '@/script'
import { SLEEP_STATUS, useUserStore } from '@/stores/user'

const props = defineProps({
  sleepStatus: { type: Number, required: true },
  sleepFrom: { type: String, default: null },
  sleepUntil: { type: String, default: null },
  sleepMessage: { type: String, default: '' },
})

const labelsCalendar = {
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
}
const sleepingOptions = [
  { value: SLEEP_STATUS.NONE, text: i18n('settings.sleep.none') },
  { value: SLEEP_STATUS.TEMP, text: i18n('settings.sleep.temp') },
  { value: SLEEP_STATUS.FULL, text: i18n('settings.sleep.full') },
]

const maxlengthSleepingMessage = 5000

const currentSleepStatus = ref(props.sleepStatus)
const currentSleepFrom = ref(props.sleepFrom)
const currentSleepUntil = ref(props.sleepUntil)
const currentSleepMessage = ref(props.sleepMessage)
const startDate = ref(new Date())
const isLoading = ref(false)

function isSleepDateValid (date) {
  return date !== null && !isNaN(Date.parse(date))
}

function isSleepStatusValid () {
  return currentSleepStatus.value >= 0 && currentSleepStatus.value <= 2
}

function sendButtonIsValid () {
  switch (currentSleepStatus.value) {
    case SLEEP_STATUS.NONE:
      return isSleepStatusValid()
    case SLEEP_STATUS.TEMP:
      return isSleepStatusValid && isSleepDateValid(currentSleepFrom.value) && isSleepDateValid(currentSleepUntil.value)
    case SLEEP_STATUS.FULL:
      return isSleepDateValid(startDate.value) && isSleepStatusValid
    default:
      return false
  }
}

async function trySetSleepStatus () {
  isLoading.value = true

  const sendingData = {
    status: null,
    from: null,
    until: null,
    message: null,
  }

  switch (currentSleepStatus.value) {
    case SLEEP_STATUS.NONE:
      sendingData.status = currentSleepStatus.value
      break
    case SLEEP_STATUS.TEMP:
      sendingData.status = currentSleepStatus.value
      sendingData.from = currentSleepFrom.value
      sendingData.until = currentSleepUntil.value
      sendingData.message = currentSleepMessage.value
      break
    case SLEEP_STATUS.FULL:
      sendingData.status = currentSleepStatus.value
      sendingData.from = startDate.value
      sendingData.message = currentSleepMessage.value
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

  // Update user store
  const userStore = useUserStore()
  await userStore.fetchDetails(true /* force */)

  isLoading.value = false
}
</script>
