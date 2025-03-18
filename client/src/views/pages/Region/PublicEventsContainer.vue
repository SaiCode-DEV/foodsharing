<template>
  <Container
    v-if="props.events.length"
    :title="$i18n('region.public.events.title')"
    tag="publicRegionEvents"
  >
    <div
      v-if="!currentEvents.length"
      class="list-group-item"
      v-text="$i18n('region.public.events.no_future_events')"
    />
    <EventPanel
      v-for="event in currentEvents"
      :key="event.id"
      class="list-group-item py-0"
      :event="event"
      :status="-1"
    />
    <EventPanel
      v-for="event in pastEvents.slice(0, numberOfDisplayedPastEvents)"
      :key="event.id"
      class="list-group-item py-0"
      :event="event"
      :status="-1"
    />
    <ContainerButton
      v-if="numberOfDisplayedPastEvents < pastEvents.length"
      variant="success"
      :text-key="numberOfDisplayedPastEvents ? 'globals.show_more' : 'region.public.events.load_past'"
      icon="fas fa-calendar"
      @click="numberOfDisplayedPastEvents += 10"
    />
  </Container>
</template>
<script setup>
import { defineProps, ref } from 'vue'
import Container from '@/components/Container/Container.vue'
import EventPanel from '@php/Modules/Event/components/EventPanel.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'

const props = defineProps({
  events: { type: Array, required: true },
})

const now = new Date()
const pastEvents = ref(props.events.filter(event => new Date(event.endDate) < now))
const currentEvents = ref(props.events.filter(event => new Date(event.endDate) > now))
const numberOfDisplayedPastEvents = ref(currentEvents.value.length ? 0 : 10)
</script>
