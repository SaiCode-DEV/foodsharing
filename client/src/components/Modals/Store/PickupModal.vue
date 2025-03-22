<template>
  <div>
    <b-modal
      :id="modalId"
      :title="title"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.send')"
      :ok-disabled="isDateTimeNotSelected"
      @ok="trySetPickupSlots"
    >
      <p>{{ description }}</p>
      {{ $i18n('day') }}
      <b-form-datepicker
        v-model="selectedSlotDate"
        v-bind="labelsCalendar || {}"
        :min="minSlotDate"
        :locale="$i18n('calendar.locale')"
        menu-class="w-100"
        calendar-width="100%"
        class="mb-2"
        start-weekday="1"
      />
      {{ $i18n('time') }}
      <b-form-input
        v-model="selectedSlotTime"
        type="time"
        placeholder="HH:mm"
      />
      <div
        v-if="!deletePickupMode"
        class="pt-2"
      >
        {{ $i18n('pickup.edit.description_titel') }}

        <b-form-input
          v-model="slotDescription"
          :placeholder="$i18n('pickup.description_optional')"
          :maxlength="100"
        />
        <small v-if="slotDescription?.length === 100">
          <i class="fas fa-info-circle" />
          {{ $i18n('pickup.description_max_length_info') }}
        </small>
      </div>
      <div
        v-if="!deletePickupMode"
        class="pt-2"
      >
        {{ $i18n('pickup.edit.slot_titel') }}
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
import PickupsData from '@/stores/pickups'
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
      storeStore: useStoreStore(),
    }
  },
  data () {
    return {
      locale: locale,
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
        await PickupsData.mutations.loadPickups(this.storeId)
      } catch (err) {
        const errorDescription = err.jsonContent ?? { message: '' }
        const errorMessage = `(${errorDescription.message ?? 'Unknown'})`
        pulseError(this.$i18n('storeedit.unsuccess', { error: errorMessage }))
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
