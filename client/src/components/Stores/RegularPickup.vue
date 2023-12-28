<template>
  <b-form-group
    :label="$i18n('pickup.edit.bread')"
  >
    <b-row>
      <b-col
        cols="4"
        lg="2"
      >
        {{ $i18n('day') }}
      </b-col>

      <b-col
        cols="4"
        lg="2"
      >
        {{ $i18n('time') }}
      </b-col>

      <b-col
        cols="4"
        lg="2"
      >
        {{ $i18n('pickup.edit.slot_titel') }}
      </b-col>
    </b-row>

    <div
      v-for="(item, key, index) in editPickupsCopied"
      :key="key"
    >
      <br v-if="index !== 0">
      <b-row class="pb-1">
        <b-col
          cols="4"
          lg="2"
          class="pr-1 pl-1 mb-1"
        >
          <b-form-select
            v-model="item.weekday"
            size="sm"
            :disabled="!editMode"
          >
            <option
              v-for="weekday in weekdays"
              :key="weekday.value"
              :value="weekday.value"
            >
              {{ weekday.text }}
            </option>
          </b-form-select>
        </b-col>

        <b-col
          cols="4"
          lg="2"
          class="pr-1 pl-1"
        >
          <b-form-timepicker
            v-model="item.startTimeOfPickup"
            :locale="locale"
            v-bind="labelsTimepicker || {}"
            size="sm"
            minutes-step="5"
            :disabled="!editMode"
          />
        </b-col>

        <b-col
          cols="4"
          lg="2"
          class="pr-1 pl-1"
        >
          <b-form-spinbutton
            v-model.number="item.maxCountOfSlots"
            :disabled="!editMode"
            size="sm"
            :max="maxCountPickupSlot"
            :min="minCountPickupSlot"
          />
        </b-col>
        <b-col
          cols="10"
          lg="5"
          class="pr-1 pl-1"
        >
          <b-form-input
            v-model="item.description"
            :disabled="!editMode"
            :size="'sm'"
            :placeholder="$i18n('pickup.description')"
            :maxlength="100"
          />
          <small v-if="item.description?.length === 100">
            <i class="fas fa-info-circle" />
            {{ $i18n('pickup.description_max_length_info') }}
          </small>
        </b-col>
        <b-col
          cols="2"
          lg="1"
          class="pr-1 pl-1"
          align="right"
        >
          <b-button
            variant="danger"
            :hidden="!editMode"
            size="sm"
            @click="removePickup(key)"
          >
            <i class="fa fa-trash" />
          </b-button>
        </b-col>
      </b-row>
    </div>
    <b-button
      variant="outline-primary"
      class="mt-3"
      :hidden="!editMode"
      @click="addNewItem"
    >
      +
    </b-button>
  </b-form-group>
</template>

<script>
import Vue from 'vue'
import i18n, { locale } from '@/helper/i18n'

export default {
  props: {
    loadedPickups: {
      type: [Array, Object],
      default: () => [],
      required: true,
    },
    editMode: { type: Boolean, default: false },
    maxCountPickupSlot: { type: Number, default: 0 },
  },
  data () {
    return {
      editPickupsCopied: {},
      minCountPickupSlot: 1,
      weekdays: [
        { value: 1, text: this.$i18n('date.monday') },
        { value: 2, text: this.$i18n('date.tuesday') },
        { value: 3, text: this.$i18n('date.wednesday') },
        { value: 4, text: this.$i18n('date.thursday') },
        { value: 5, text: this.$i18n('date.friday') },
        { value: 6, text: this.$i18n('date.saturday') },
        { value: 0, text: this.$i18n('date.sunday') },
      ],
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
    }
  },
  computed: {
    console: () => console,
  },
  async created () {
    this.editPickupsCopied = this.loadedPickups
  },
  methods: {
    removePickup (key) {
      Vue.delete(this.editPickupsCopied, key)
    },
    timeParser (value) {
      return value ? value + ':00' : ''
    },
    addNewItem () {
      const selectedWeekdays = Object.values(this.editPickupsCopied).map(item => item.weekday)
      const availableWeekdays = this.weekdays.filter(weekday => !selectedWeekdays.includes(weekday.value))
      const nextWeekday = availableWeekdays[0]?.value ?? 1

      const newIndex = Object.keys(this.editPickupsCopied).length
      const newPickup = {
        weekday: nextWeekday,
        startTimeOfPickup: this.timeParser('10:30'),
        maxCountOfSlots: this.minCountPickupSlot,
      }

      const updatedPickups = {
        ...this.editPickupsCopied,
        [`${newIndex}`]: newPickup,
      }

      this.editPickupsCopied = updatedPickups
      this.emitEditPickups()
    },
    emitEditPickups () {
      this.$emit('update:editPickups', this.editPickupsCopied)
    },
  },
}
</script>

<style scoped lang="scss">

</style>
