<template>
  <div>
    <div class="d-flex justify-content-between align-items-center">
      <h1>{{ $i18n('pickup.overview.header') }}</h1>
      <b-button
        v-b-tooltip="$i18n('settings.calendar.export_tooltip')"
        class="d-none d-md-block"
        variant="primary"
        size="sm"
        :href="$url('settingsCalendar')"
      >
        {{ $i18n('settings.calendar.export') }}
      </b-button>
    </div>
    <b-tabs
      content-class="mt-3"
      align="left"
      nav-class="tabs-wrapper"
      nav-wrapper-class="scroll-nav-wrapper"
    >
      <PickupTab
        v-if="showRegisteredTab"
        tab-name="registered"
        :data-endpoint="listRegisteredPickups"
        :fs-id="fsId"
        :allow-slot-cancelation="allowSlotCancelation"
        init
        :is-own-profile="isOwnProfile"
      />
      <PickupTab
        v-if="showOptionsTab"
        tab-name="options"
        :data-endpoint="listPickupOptions"
        table-class="shadow-registered"
        paginated
        :is-own-profile="isOwnProfile"
      />
      <PickupTab
        v-if="showHistoryTab"
        tab-name="history"
        :data-endpoint="listPastPickups"
        :fs-id="fsId"
        paginated
        :is-own-profile="isOwnProfile"
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
  props: {
    showRegisteredTab: {
      type: Boolean,
      default: () => false,
    },
    showOptionsTab: {
      type: Boolean,
      default: () => false,
    },
    showHistoryTab: {
      type: Boolean,
      default: () => false,
    },
    fsId: {
      // the foodsaver id of the shown profile page
      type: Number,
      default: () => -1,
    },
    allowSlotCancelation: {
      // whether to allow canceling slots
      type: Boolean,
      default: () => false,
    },
    isOwnProfile: {
      // whether the profile view is shown to the owner of the profile. Used to differentiate some texts.
      type: Boolean,
      default: () => false,
    },
  },
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
