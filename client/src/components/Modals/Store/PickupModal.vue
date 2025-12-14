<template>
  <div>
    <b-modal
      :id="modalId"
      :title="title"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.send')"
      :ok-disabled="isDateTimeNotSelected"
      @ok="trySetPickupSlots"
    >
      <p>{{ description }}</p>
      {{ $t('day') }}
      <b-input-group class="mb-3">
        <b-form-input
          id="date-input"
          v-model="selectedSlotDate"
          type="date"
        />
        <b-input-group-append>
          <b-form-datepicker
            v-model="selectedSlotDate"
            v-bind="labelsCalendar || {}"
            button-only
            right
            :min="minSlotDate"
            :locale="$t('calendar.locale')"
            start-weekday="1"
            aria-controls="date-input"
          />
        </b-input-group-append>
      </b-input-group>
      {{ $t('time') }}
      <b-input-group class="mb-3">
        <b-form-input
          id="time-input"
          v-model="selectedSlotTime"
          type="time"
        />
        <b-input-group-append>
          <b-form-timepicker
            v-model="selectedSlotTime"
            :locale="locale"
            v-bind="labelsTimepicker || {}"
            button-only
            right
            minutes-step="5"
            aria-controls="time-input"
          />
        </b-input-group-append>
      </b-input-group>
      <div
        v-if="!deletePickupMode"
        class="pt-2"
      >
        {{ $t('pickup.edit.description_titel') }}

        <b-form-input
          v-model="slotDescription"
          :placeholder="$t('pickup.description_optional')"
          :maxlength="100"
        />
        <small v-if="slotDescription?.length === 100">
          <i class="fas fa-info-circle" />
          {{ $t('pickup.description_max_length_info') }}
        </small>
      </div>
      <div
        v-if="!deletePickupMode"
        class="pt-2"
      >
        {{ $t('pickup.edit.slot_titel') }}
        <b-form-spinbutton
          v-model="selectedSlotCount"
          :min="minSlotCount"
          :max="maxCountPickupSlot"
        />
      </div>
    </b-modal>
  </div>
</template>

<script>
import { setPickupSlots } from '@/api/pickups'
import i18n, { locale } from '@/helper/i18n'
import { pulseError } from '@/script'
import { usePickupStore } from '@/stores/pickups'
import { useStoreStore } from '@/stores/store'

export default {
  props: {
    storeId: { type: Number, required: true },
    title: { type: String, required: true },
    deletePickupMode: { type: Boolean, default: false },
    modalId: { type: String, required: true },
    description: { type: String, required: true },
  },
  setup () {
    return {
      pickupStore: usePickupStore(),
      storeStore: useStoreStore(),
    }
  },
  data () {
    return {
      locale,
      labelsTimepicker: {
        labelHours: i18n('timepicker.labelHours'),
        labelMinutes: i18n('timepicker.labelMinutes'),
        labelSeconds: i18n('timepicker.labelSeconds'),
        labelIncrement: i18n('timepicker.labelIncrement'),
        labelDecrement: i18n('timepicker.labelDecrement'),
        labelSelected: i18n('timepicker.labelSelected'),
        labelNoTimeSelected: i18n('timepicker.labelNoTimeSelected'),
        labelCloseButton: i18n('timepicker.labelCloseButton'),
      },
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
      selectedSlotDate: null,
      selectedSlotTime: null,
      minSlotDate: null,
      selectedSlotCount: 1,
      minSlotCount: 1,
      slotDescription: '',
    }
  },
  computed: {
    maxCountPickupSlot () {
      return this.storeStore.getMaxCountPickupSlot
    },
    isDateTimeNotSelected () {
      return !this.selectedSlotDate || !this.selectedSlotTime
    },
  },
  async created () {
    this.minSlotDate = new Date()
  },
  methods: {
    async trySetPickupSlots () {
      try {
        const combinedDateTime = new Date(this.selectedSlotDate)

        const timeParts = this.selectedSlotTime.split(':')
        const hours = parseInt(timeParts[0])
        const minutes = parseInt(timeParts[1])

        combinedDateTime.setHours(hours)
        combinedDateTime.setMinutes(minutes)
        combinedDateTime.setSeconds(0)

        if (!this.selectedSlotCount || this.slotDescription === '') {
          this.slotDescription = null
        }

        if (this.deletePickupMode) {
          this.selectedSlotCount = 0
        }

        await setPickupSlots(this.storeId, combinedDateTime, this.selectedSlotCount, this.slotDescription)
        this.pickupStore.invalidateOptionsCache()
        await this.pickupStore.loadPickups(this.storeId)
      } catch (err) {
        const errorDescription = err.jsonContent ?? { message: '' }
        const errorMessage = `(${errorDescription.message ?? 'Unknown'})`
        pulseError(this.$t('storeedit.unsuccess', { error: errorMessage }))
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.custom-modal-width {
  max-width: 300px;
  width: 100%;
}
</style>
