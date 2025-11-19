<template>
  <div>
    <div class="text-right pb-2">
      <b-button
        v-if="pickupsSection.isOwnProfile"
        v-b-tooltip="$t('settings.calendar.export_tooltip')"
        variant="primary"
        size="sm"
        :href="$url('settingsCalendar')"
      >
        {{ $t('settings.calendar.export') }}
      </b-button>
    </div>
    <b-tabs
      content-class="mt-3"
      align="left"
      nav-class="tabs-wrapper"
      nav-wrapper-class="scroll-nav-wrapper"
    >
      <PickupTab
        v-if="pickupsSection.showRegisteredTab"
        tab-name="registered"
        :data-endpoint="listRegisteredPickups"
        :fs-id="pickupsSection.fsId"
        :allow-slot-cancelation="pickupsSection.allowSlotCancelation"
        init
        :is-own-profile="pickupsSection.isOwnProfile"
      />
      <PickupTab
        v-if="pickupsSection.showOptionsTab"
        tab-name="options"
        :data-endpoint="listPickupOptions"
        table-class="shadow-registered"
        :is-own-profile="pickupsSection.isOwnProfile"
      />
      <PickupTab
        v-if="pickupsSection.showHistoryTab"
        tab-name="history"
        :data-endpoint="listPastPickups"
        :fs-id="pickupsSection.fsId"
        paginated
        :is-own-profile="pickupsSection.isOwnProfile"
      />
    </b-tabs>
  </div>
</template>

<script>
import { BTabs } from 'bootstrap-vue'
import PickupTab from './PickupTab.vue'
import { listRegisteredPickups, listPickupOptions, listPastPickups } from '@/api/pickups'

export default {
  components: { BTabs, PickupTab },
  props: { pickupsSection: { type: Object, required: true } },
  data () {
    return {
      listRegisteredPickups,
      listPickupOptions,
      listPastPickups,
    }
  },
}

</script>

<style lang="scss">
.tabs-wrapper {
  margin: 0 -10px;
}

.scroll-nav-wrapper {
  overflow-x: auto;
  .tabs-wrapper {
    flex-wrap: nowrap;
    display: inline-flex;
    min-width: 100%;
    .tab-item {
      white-space: nowrap;
    }
  }
}

</style>
