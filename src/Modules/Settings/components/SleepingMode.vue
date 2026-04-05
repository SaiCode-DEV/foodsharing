<template>
  <div>
    <div
      class="alert alert-info"
      role="alert"
    >
      {{ $t('settings.sleep.info') }}
    </div>

    <label for="sleep-status">{{ $t('settings.sleep.status') }}</label>
    <b-form-select
      id="sleep-status"
      v-model="currentSleepStatus"
      :options="sleepingOptions"
    />

    <div
      v-if="currentSleepStatus === SLEEP_STATUS.TEMP"
      class="mt-3"
    >
      <label for="sleep-from">{{ $t('settings.sleep.range') }}</label>
      <b-row>
        <b-col
          cols="12"
          lg="6"
        >
          <b-form-group
            :label="$t('settings.sleep.from')"
            label-for="sleep-from"
            class="mb-0"
          >
            <b-form-datepicker
              id="sleep-from"
              v-model="currentSleepFrom"
              :date-disabled-fn="(_, date) => !isDateValidForSleepFrom(date)"
              v-bind="labelsCalendar || {}"
              :locale="locale"
              :state="!v$.currentSleepFromDate.$error"
              @hidden="v$.currentSleepFromDate.$touch"
            />
            <div
              v-if="v$.currentSleepFromDate.$error"
              class="invalid-feedback d-block"
            >
              <div v-if="v$.currentSleepFromDate.required.$invalid">
                {{ $t('settings.sleep.missing-date') }}
              </div>
              <div v-else-if="activeSleepFromDate">
                {{ $t('settings.sleep.start_date_same_or_future') }}
              </div>
              <div v-else>
                {{ $t('settings.sleep.start_date_invalid') }}
              </div>
            </div>
          </b-form-group>
        </b-col>
        <b-col
          cols="12"
          lg="6"
        >
          <b-form-group
            :label="$t('settings.sleep.until')"
            label-for="sleep-until"
            class="mb-0"
          >
            <b-form-datepicker
              id="sleep-until"
              v-model="currentSleepUntil"
              v-bind="labelsCalendar || {}"
              :min="earliestAllowedSleepUntilDate"
              :state="!v$.currentSleepUntilDate.$error"
              :locale="locale"
              @hidden="v$.currentSleepUntilDate.$touch"
            />
            <div
              v-if="v$.currentSleepUntilDate.$error"
              class="invalid-feedback d-block"
            >
              <div v-if="v$.currentSleepUntilDate.required.$invalid">
                {{ $t('settings.sleep.missing-date') }}
              </div>
              <div v-else>
                {{ $t('settings.sleep.end_date_invalid') }}
              </div>
            </div>
          </b-form-group>
        </b-col>
      </b-row>
    </div>

    <div
      v-if="currentSleepStatus > SLEEP_STATUS.NONE"
      class="mt-3"
    >
      <b-form-group
        :label="$t('settings.sleep.message')"
        label-for="sleep-message"
      >
        <b-form-textarea
          id="sleep-message"
          v-model="currentSleepMessage"
          rows="3"
          max-rows="6"
          :maxlength="maxlengthSleepingMessage"
          :state="!v$.currentSleepMessage.$invalid"
        />
        <small :class="v$.currentSleepMessage.$invalid ? 'invalid-feedback' : 'text-muted'">
          {{ sleepMessageLengthInfo }}
        </small>
      </b-form-group>
    </div>

    <div class="mt-3">
      <div
        class="alert alert-warning"
        role="alert"
      >
        {{ $t('settings.sleep.show') }}
      </div>
    </div>

    <b-button
      :disabled="isLoading || v$.$invalid"
      variant="primary"
      @click="trySetSleepStatus"
    >
      {{ $t('globals.save') }}
    </b-button>
  </div>
</template>

<script setup>
import { defineProps, ref, computed, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, requiredIf, minValue, maxValue, maxLength } from '@vuelidate/validators'
import { setSleepStatus } from '@/api/user'
import i18n, { locale } from '@/helper/i18n'
import { pulseError, pulseSuccess } from '@/script'
import { SLEEP_STATUS, useUserStore } from '@/stores/user'
import dateFormatter from '@/helper/date-formatter'

const props = defineProps({
  sleepData: { type: Object, required: true },
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
const startOfToday = new Date()
startOfToday.setHours(0, 0, 0, 0)

const isLoading = ref(false)
const currentSleepStatus = ref(props.sleepData?.sleep_status)
const currentSleepFrom = ref(props.sleepData?.sleep_from)
const currentSleepUntil = ref(props.sleepData?.sleep_until)
const currentSleepMessage = ref(props.sleepData?.sleep_msg)

watch(() => props.sleepData, (newSleepData) => {
  currentSleepStatus.value = newSleepData?.sleep_status
  currentSleepFrom.value = newSleepData?.sleep_from
  currentSleepUntil.value = newSleepData?.sleep_until
  currentSleepMessage.value = newSleepData?.sleep_msg
})

const activeSleepFromDate = computed(() => props.sleepData?.sleep_from)
const currentSleepFromDate = computed(() => currentSleepFrom.value ? new Date(currentSleepFrom.value) : null)
const currentSleepUntilDate = computed(() => currentSleepUntil.value ? new Date(currentSleepUntil.value) : null)

const earliestAllowedSleepUntilDate = computed(() => {
  return currentSleepFromDate.value > startOfToday ? currentSleepFromDate.value : startOfToday
})

const rules = computed(() => {
  const areDatesRequired = currentSleepStatus.value === SLEEP_STATUS.TEMP
  return {
    currentSleepStatus: {
      required,
      minValue: minValue(SLEEP_STATUS.NONE),
      maxValue: maxValue(SLEEP_STATUS.FULL),
    },
    currentSleepFromDate: {
      required: requiredIf(areDatesRequired),
      dateValid: areDatesRequired ? isDateValidForSleepFrom : () => true,
    },
    currentSleepUntilDate: {
      required: requiredIf(areDatesRequired),
      minValue: areDatesRequired ? minValue(earliestAllowedSleepUntilDate.value) : () => true,
    },
    currentSleepMessage: {
      maxLength: maxLength(maxlengthSleepingMessage),
    },
  }
})
const v$ = useVuelidate(rules, {
  currentSleepStatus,
  currentSleepFromDate,
  currentSleepUntilDate,
  currentSleepMessage,
})

const sleepMessageLengthInfo = computed(() => {
  const characterCount = currentSleepMessage.value ? currentSleepMessage.value.length : 0
  const remaining = maxlengthSleepingMessage - characterCount
  return i18n('storeview.public_info.available_count') + ': ' + remaining
})

function isDateValidForSleepFrom (date) {
  return date >= startOfToday || dateFormatter.isSame(date, activeSleepFromDate.value)
}

async function trySetSleepStatus () {
  isLoading.value = true

  const status = currentSleepStatus.value
  const sendingData = {
    status,
    from: status === SLEEP_STATUS.TEMP ? currentSleepFrom.value : null,
    until: status === SLEEP_STATUS.TEMP ? currentSleepUntil.value : null,
    message: status !== SLEEP_STATUS.NONE ? currentSleepMessage.value : null,
  }

  try {
    await setSleepStatus(sendingData.status, sendingData.from, sendingData.until, sendingData.message)
    pulseSuccess(i18n('success'))

    // Update user store
    const userStore = useUserStore()
    await userStore.fetchProfileSettings(true /* force */)
  } catch (e) {
    pulseError(i18n('error_unexpected'))
  }

  isLoading.value = false
}
</script>
