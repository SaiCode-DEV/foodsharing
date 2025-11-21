<template>
  <Container
    :title="edit ? $t('events.edit') : $t('events.create.title')"
    wrap-content
    :collapsible="false"
  >
    <b-form>
      <b-form-group :label="$t('events.create.who')">
        <b-form-select
          v-model="event.regionId"
          :options="regionSelectOptions"
          @change="updateEventPublicState"
        />
      </b-form-group>

      <b-form-group :label="$t('events.create.name')">
        <b-form-input
          v-model.trim="event.name"
        />
      </b-form-group>

      <b-form-group :label="$t('events.create.date')">
        <b-form-checkbox
          v-model="multipleDays"
          class="pb-2"
          @change="initEndDay"
        >
          {{ $t('events.create.multiday') }}
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

      <b-form-group :label="$t('events.create.time')">
        <TimeRangePicker
          :independent="multipleDays && event.startDay < event.endDay"
          :from-time.sync="event.startTime"
          :to-time.sync="event.endTime"
        />
      </b-form-group>

      <b-form-group :label="$t('events.create.desc')">
        <MarkdownInput
          :value.sync="event.description"
          :conceal-toolbar="true"
          variant="outline-primary"
          :region-id="regionId"
          :draft-storage-id="'event-description-' + event.regionId"
        />
      </b-form-group>

      <b-form-group>
        <template #label>
          {{ $t('events.create.public') }}
          <Info info-key="publicEvent" />
        </template>
        <b-form-checkbox
          v-model="event.isPublic"
          class="pb-2"
          :disabled="!mayChangeEventPublicState"
        >
          {{ $t('events.create.isPublic') }}
        </b-form-checkbox>
      </b-form-group>

      <b-form-group :label="$t('events.create.type')">
        <b-form-select
          v-model="event.type"
          :options="typeSelectOptions"
        />
      </b-form-group>

      <b-form-group v-if="event.type === EVENT_TYPE.OFFLINE" :label="$t('addresspicker.label')">
        <LeafletLocationSearch
          :zoom="16"
          :coordinates="location"
          :postal-code="event.address.postalCode"
          :city="event.address.city"
          :street="event.address.street"
          :marker-type="MARKER_TYPES.events"
          allow-address-correction
          @address-change="onAddressChanged"
        />
      </b-form-group>

      <b-form-group
        v-if="event.type !== EVENT_TYPE.ONLINE"
        :label="$t(`events.create.${event.type ? 'locationOther' : 'locationDetails'}`)"
      >
        <b-form-input v-model="event.locationDetails" />
      </b-form-group>

      <b-alert v-else show>
        <i class="fas fa-info-circle" />
        <Markdown :source="$t('events.online_info')" />
      </b-alert>

      <span v-b-tooltip="isDataValid ? '' : $t('events.create.fillCompletely')" class="float-right">
        <b-button
          variant="success"
          :disabled="!isDataValid || submitting"
          @click="submit"
        >
          <i class="fas fa-calendar-alt pr-2" />
          {{ $t(edit ? 'events.edit' : 'events.add_new_event') }}
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
import { useRegionStore } from '@/stores/regions'
import { useUserStore } from '@/stores/user'
import { addEvent, editEvent } from '@/api/events'
import { toISOStringWithTimezone } from '@/helper/date-formatter'
import { EVENT_TYPE } from '@/consts'
import { MARKER_TYPES } from '@/stores/map'
import Info from '../Help/Info.vue'

const userStore = useUserStore()
const regionStore = useRegionStore()

export default {
  components: { Container, DateRangePicker, DatePicker, TimeRangePicker, MarkdownInput, LeafletLocationSearch, Markdown, Info },
  props: {
    regionId: { type: Number, default: 0 },
    edit: { type: Object, default: null },
  },
  setup () {
    return {
      userStore,
    }
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
          isPublic: false,
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
        isPublic: this.edit.isPublic,
      },
    }
  },
  computed: {
    EVENT_TYPE: () => EVENT_TYPE,
    MARKER_TYPES: () => MARKER_TYPES,
    groups: () => DataGroups.getters.get(),
    regions: () => regionStore.regions,
    location: () => userStore.getLocations,
    mayChangeEventPublicState () {
      const region = this.regions.find(region => region.id === this.event.regionId)
      if (region) return region?.maySetRegionPin
      return this.groups.find(groups => groups.id === this.event.regionId)?.isAdmin
    },
    regionSelectOptions () {
      return [
        {
          label: this.$t('events.create.groups'),
          options: this.groups.map(group => ({
            value: group.id,
            text: group.name,
          })),
        },
        {
          label: this.$t('events.create.regions'),
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
        text: this.$t(`events.create.${key}`),
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
      this.event.address.postalCode = postalCode
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
    updateEventPublicState () {
      if (!this.mayChangeEventPublicState) {
        this.event.isPublic = false
      }
    },
  },
}
</script>
