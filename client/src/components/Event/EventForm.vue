<template>
  <Container
    :title="$i18n('events.create.title')"
    wrap-content
    :collapsible="false"
  >
    <b-form>
      <b-form-group :label="$i18n('events.create.who')">
        <b-form-select
          v-model="event.regionId"
          :options="regionSelectOptions"
        />
      </b-form-group>

      <b-form-group :label="$i18n('events.create.name')">
        <b-form-input
          v-model.trim="event.name"
        />
      </b-form-group>

      <b-form-group :label="$i18n('events.create.date')">
        <b-form-checkbox
          v-model="multipleDays"
          class="pb-2"
          @change="initEndDay"
        >
          {{ $i18n('events.create.multiday') }}
        </b-form-checkbox>
        <DatePicker
          v-if="!multipleDays"
          v-model="event.startDay"
          :min="new Date()"
        />
        <DateRangePicker
          v-else
          block
          :min-from-date="new Date()"
          :from-date.sync="event.startDay"
          :to-date.sync="event.endDay"
        />
      </b-form-group>

      <b-form-group :label="$i18n('events.create.time')">
        <TimeRangePicker
          :independent="multipleDays && event.startDay < event.endDay"
          :from-time.sync="event.startTime"
          :to-time.sync="event.endTime"
        />
      </b-form-group>

      <b-form-group :label="$i18n('events.create.desc')">
        <MarkdownInput
          :value.sync="event.description"
          :conceal-toolbar="true"
          variant="outline-primary"
        />
      </b-form-group>

      <b-form-group :label="$i18n('events.create.type')">
        <b-form-select
          v-model="event.type"
          :options="typeSelectOptions"
        />
      </b-form-group>

      <b-form-group v-if="event.type === EVENT_TYPE.OFFLINE" :label="$i18n('addresspicker.label')">
        <LeafletLocationSearch
          :zoom="16"
          :coordinates="location"
          icon-name="calendar-alt"
          :postal-code="event.address.zipCode"
          :city="event.address.city"
          :street="event.address.street"
          @address-change="onAddressChanged"
        />
      </b-form-group>

      <b-form-group
        v-if="event.type !== EVENT_TYPE.ONLINE"
        :label="$i18n(`events.create.${event.type ? 'locationOther' : 'locationDetails'}`)"
      >
        <b-form-input v-model="event.locationDetails" />
      </b-form-group>

      <b-alert v-else show>
        <i class="fas fa-info-circle" />
        <Markdown :source="$i18n('events.online_info')" />
      </b-alert>

      <span v-b-tooltip="isDataValid ? '' : $i18n('events.create.fillCompletely')" class="float-right">
        <b-button
          variant="success"
          :disabled="!isDataValid || submitting"
          @click="submit"
        >
          <i class="fas fa-calendar-alt pr-2" />
          {{ $i18n(edit ? 'events.edit' : 'events.add_new_event') }}
        </b-button>
      </span>
    </b-form>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import DateRangePicker from '@/components/DateTime/DateRangePicker.vue'
import DatePicker from '@/components/DateTime/DatePicker.vue'
import TimeRangePicker from '@/components/DateTime/TimeRangePicker.vue'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'
import LeafletLocationSearch from '@/components/map/LeafletLocationSearch.vue'
import Markdown from '@/components/Markdown/Markdown.vue'

import DataGroups from '@/stores/groups'
import DataRegions from '@/stores/regions'
import DataUser from '@/stores/user'
import { addEvent, editEvent } from '@/api/events'
import { toISOStringWithTimezone } from '@/helper/date-formatter'
import { EVENT_TYPE } from '@/consts'

export default {
  components: { Container, DateRangePicker, DatePicker, TimeRangePicker, MarkdownInput, LeafletLocationSearch, Markdown },
  props: {
    regionId: { type: Number, default: 0 },
    edit: { type: Object, default: null },
  },
  data () {
    if (!this.edit) {
      return {
        multipleDays: false,
        submitting: false,
        event: {
          regionId: this.regionId,
          startDay: null,
          endDay: null,
          startTime: null,
          endTime: null,
          type: 0,
          location: {},
          address: {},
          locationDetails: '',
        },
      }
    }
    const [start, end] = [this.edit.startDate, this.edit.endDate].map(date => new Date(date))
    return {
      multipleDays: start.toDateString() !== end.toDateString(),
      submitting: false,
      event: {
        regionId: this.edit.regionId,
        name: this.edit.name,
        description: this.edit.description,
        startTime: start.toTimeString().slice(0, 5),
        startDay: new Date(start.setHours(0, 0, 0, 0)),
        endTime: end.toTimeString().slice(0, 5),
        endDay: new Date(end.setHours(0, 0, 0, 0)),
        type: this.edit.type,
        location: this.edit.location ?? {},
        address: this.edit.address ?? {},
        locationDetails: this.edit.locationDetails ?? '',
      },
    }
  },
  computed: {
    EVENT_TYPE: () => EVENT_TYPE,
    groups: () => DataGroups.getters.get(),
    regions: () => DataRegions.getters.get(),
    location: () => DataUser.getters.getLocations(),
    regionSelectOptions () {
      return [
        {
          label: this.$i18n('events.create.groups'),
          options: this.groups.map(group => ({
            value: group.id,
            text: group.name,
          })),
        },
        {
          label: this.$i18n('events.create.regions'),
          options: this.regions.map(region => ({
            value: region.id,
            text: region.name,
          })),
        },
      ]
    },
    typeSelectOptions () {
      return ['offline', 'online', 'other'].map((key, i) => ({
        value: i,
        text: this.$i18n(`events.create.${key}`),
      }))
    },
    startDate () {
      return this.mergeTimeAndDay(this.event.startDay, this.event.startTime)
    },
    endDate () {
      const day = this.multipleDays ? this.event.endDay : this.event.startDay
      return this.mergeTimeAndDay(day, this.event.endTime)
    },
    isDataValid () {
      if (!this.event.regionId > 0) return false
      if (!this.event.name) return false
      if (!this.startDate?.getTime?.()) return false
      if (!this.endDate?.getTime?.()) return false
      if (this.startDate > this.endDate) return false
      if (!this.event.description) return false
      switch (this.event.type) {
        case EVENT_TYPE.OFFLINE:
          if (!this.event.location.lat || !this.event.location.lon) return false
          if (!this.event.address.street || !this.event.address.city) return false
          break
        case EVENT_TYPE.OTHER:
          if (!this.event.locationDetails) return false
          break
      }
      return true
    },
  },
  methods: {
    initEndDay () {
      if (!this.event.startDay) return
      if (!this.event.endDay) {
        this.event.endDay = new Date(this.event.startDay)
        this.event.endDay.setDate(this.event.endDay.getDate() + 1)
      }
      if (this.event.endDay < this.event.startDay) {
        this.event.endDay = new Date(this.event.startDay)
      }
    },
    onAddressChanged (coordinates, street, postalCode, city) {
      this.event.location = coordinates
      this.event.address.street = street
      this.event.address.zipCode = postalCode
      this.event.address.city = city
    },
    mergeTimeAndDay (day, time) {
      try {
        const date = new Date(day)
        date.setHours(...time.split(':').map(Number))
        return date
      } catch {
        return null
      }
    },
    async submit () {
      this.submitting = true
      const event = this.prepareEventData()
      if (this.edit) {
        event.id = this.edit.id
        await editEvent(event)
        location.replace(this.$url('event', event.id))
      } else {
        const eventId = await addEvent(event)
        location.replace(this.$url('event', eventId))
      }
    },
    prepareEventData () {
      const event = Object.assign({}, this.event, {
        startDate: toISOStringWithTimezone(this.startDate),
        endDate: toISOStringWithTimezone(this.endDate),
      })
      delete event.startDay
      delete event.endDay
      delete event.startTime
      delete event.endTime
      if (this.event.type !== EVENT_TYPE.OFFLINE) {
        delete event.location
        delete event.address
      }
      if (this.event.type === EVENT_TYPE.ONLINE) {
        delete event.locationDetails
      }
      return event
    },
  },
}
</script>
