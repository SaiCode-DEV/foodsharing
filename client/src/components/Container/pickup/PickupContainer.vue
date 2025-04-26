<template>
  <Container
    v-if="pickups.length"
    tag="registered-pickups"
    :title="$i18n('dashboard.pickupdates')"
  >
    <PaginatedContent :items="pickups">
      <template #default="{ currentPageItems }">
        <PickupField
          v-for="(entry, key) in currentPageItems"
          :key="key"
          :entry="entry"
        />
      </template>
    </PaginatedContent>
  </Container>
</template>

<script>
import { usePickupStore } from '@/stores/pickups'
import Container from '../Container.vue'
import PickupField from './PickupField'
import PaginatedContent from '../PaginatedContent.vue'

export default {
  components: { Container, PickupField, PaginatedContent },
  setup () {
    return {
      pickupStore: usePickupStore(),
    }
  },
  computed: {
    pickups () {
      return this.pickupStore.getRegistered
    },
  },
  mounted () {
    this.pickupStore.fetchRegistered()
  },
}
</script>
