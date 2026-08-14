<template>
  <b-list-group-item
    :to="$url('poll', poll.id)"
    class="d-flex"
  >
    <CalendarDate :date-object="new Date(poll.endDate)" />
    <div class="ml-2">
      <b v-text="poll.name" />
      <b-badge
        v-if="currentlyRunning"
        class="ml-2"
        pill
        :variant="variant"
      >
        <span v-if="!poll.isEligible" v-text="$t('polls.badge.not_eligible')" />
        <span v-else-if="poll.hasVoted" v-text="$t('polls.badge.already_voted')" />
        <span v-else v-text="$t('polls.badge.vote_now')" />
      </b-badge>
      <div class="mt-2">
        {{ $dateFormatter.dateTime(new Date(poll.startDate)) }} - {{ $dateFormatter.dateTime(new Date(poll.endDate)) }}
      </div>
    </div>
  </b-list-group-item>
</template>
<script setup>
import { defineProps } from 'vue'
import CalendarDate from '@/components/CalendarDate.vue'

const props = defineProps({
  poll: { type: Object, required: true },
})

const now = new Date()
const currentlyRunning = new Date(props.poll.startDate) < now && now < new Date(props.poll.endDate)
let variant = 'danger'
if (props.poll.hasVoted) variant = 'primary'
else if (props.poll.isEligible) variant = 'success'
</script>
