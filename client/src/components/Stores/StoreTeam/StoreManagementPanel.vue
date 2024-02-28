<template>
  <div class="ui-corner-top" :class="classes">
    <span class="text-muted">{{ $i18n('store.sm.makeRegularTeamMember') }}</span>
    <user-search-input
      id="new-foodsaver-search"
      :placeholder="$i18n('store.sm.searchPlaceholder')"
      button-icon="fa-user-plus"
      :button-tooltip="$i18n('store.sm.makeRegularTeamMember')"
      :filter="filterNotInTeam"
      :region-id="regionId"
      @user-selected="addNewTeamMember"
    />

    <hr>

    <span class="text-muted">{{ $i18n('store.sm.managementEffect') }}</span>

    <b-button-toolbar
      class="flex-md-column p-1 d-none"
      key-nav
      justify
      :aria-label="$i18n('store.sm.managementActions')"
    >
      <b-button-group
        v-b-tooltip.hover.top="$i18n('store.sm.byLastPickup')"
        size="sm"
        class="m-1 manage-sort"
      >
        <b-button
          variant="secondary"
          disabled
          class="last-pickup"
        >
          <i class="fas fa-fw fa-user-clock" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.pickupDesc')"
          variant="light"
          class="last-pickup descending"
        >
          <i class="fas fa-fw fa-chevron-down" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.pickupAsc')"
          variant="light"
          class="last-pickup ascending"
        >
          <i class="fas fa-fw fa-chevron-up" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.pickupReset')"
          variant="light"
          class="last-pickup reset"
        >
          <i class="fas fa-fw fa-sort" />
        </b-button>
      </b-button-group>

      <b-button-group
        v-b-tooltip.hover.top="$i18n('store.sm.filter')"
        size="sm"
        class="m-1 manage-filter"
      >
        <b-button
          variant="warning"
          disabled
          class="filter"
        >
          <i class="fas fa-fw fa-filter" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.filterJumper')"
          variant="light"
          class="filter-jumper"
        >
          <i class="fas fa-fw fa-star" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.filterUnverified')"
          variant="light"
          class="filter-unverified"
        >
          <i class="fas fa-fw fa-eye-slash" />
        </b-button>
        <b-button
          v-b-tooltip.hover.bottom="$i18n('store.sm.filterQuizSM')"
          variant="light"
          class="filter-storemanager-quiz"
        >
          <i class="fas fa-fw fa-store-alt" />
        </b-button>
      </b-button-group>
    </b-button-toolbar>
  </div>
</template>

<script>
import { addStoreMember } from '@/api/stores'
import { reload, pulseError, showLoader, hideLoader } from '@/script'
import UserSearchInput from '@/components/UserSearchInput.vue'
import StoreData from '@/stores/stores'

export default {
  components: { UserSearchInput },
  props: {
    classes: { type: String, default: '' },
    storeId: { type: Number, required: true },
    team: { type: Array, required: true },
    regionId: { type: Number, required: true },
  },
  data () {
    return {
      // active sorting controls
      // active filtering controls
      requireReload: false,
    }
  },
  methods: {
    reload,
    async addNewTeamMember (userId) {
      showLoader()
      try {
        await addStoreMember(this.storeId, userId)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      hideLoader()
    },
    filterNotInTeam (userId) {
      return !this.team.some(x => x.id === userId)
    },
  },
}
</script>

<style lang="scss" scoped>
</style>
