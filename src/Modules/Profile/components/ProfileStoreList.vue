<template>
  <div>
    <b-button-group class="d-flex">
      <b-button
        v-for="button of filterButtons"
        :key="button.state"
        v-b-tooltip.hover="$i18n(`profile.stores.memberState.${button.tooltip}`)"
        size="sm"
        variant="outline-primary"
        :pressed="button.state === filterMemberState"
        class="flex-grow-1"
        @click="setMemberState(button)"
      >
        <i :class="`fas fa-${button.icon}`" />
        {{ button.count }}
      </b-button>
    </b-button-group>
    <div class="d-flex mb-2">
      <b-form-input
        v-model="filterName"
        :placeholder="$i18n('profile.stores.search_placeholder')"
      />
      <b-button
        variant="outline-primary"
        @click="resetFilter"
      >
        <i class="fas fa-times" />
      </b-button>
    </div>
    <div
      v-for="store in filteredStoresAndPagination"
      :key="store.id"
    >
      <a
        href="#"
        @click="filterToState(store.cooperationStatus)"
      ><StoreStatusIcon :cooperation-status="store.cooperationStatus" /></a>
      <a :href="$url('store', store.id)">
        {{ store.name }}
      </a>
      <div class="pl-3 pb-1">
        <i
          v-b-tooltip.hover="getIconAndTooltip(store).tooltipText"
          :class="getIconAndTooltip(store).iconClass"
        />
        <a
          href="#"
          @click="filterToRegion(store.regionId)"
        >
          {{ store.regionName }}
        </a>
      </div>
    </div>
    <div class="float-right p-1 pr-3">
      <b-pagination
        v-if="filteredStores.length > 0"
        v-model="currentPage"
        :total-rows="filteredStores.length"
        :per-page="perPage"
        class="my-0"
      />
    </div>
    <div class="pt-4">
      <b-button variant="outline-secondary" :href="$url('storeUserList', userId)">
        {{ $i18n('profile.stores.store_table') }}
      </b-button>
    </div>
  </div>
</template>

<script>
import StoreStatusIcon from '../../Store/components/StoreStatusIcon.vue'
import { COOPERATION_STATUS } from '@/stores/stores'
import { PROFILE_STORE_TEAM_STATE } from '@/stores/profiles'

export default {
  components: { StoreStatusIcon },
  props: {
    stores: { type: Array, default: () => { return [] } },
    userId: { type: Number, required: true },
  },
  data () {
    return {
      filterButtons: [
        { tooltip: 'filterAll', state: null, icon: 'users' },
        { tooltip: 'filterManage', state: PROFILE_STORE_TEAM_STATE.MANAGE_ROLE, icon: 'fas fa-user-cog' },
        { tooltip: 'filterActive', state: PROFILE_STORE_TEAM_STATE.ACTIVE, icon: 'user' },
        { tooltip: 'filterJumper', state: PROFILE_STORE_TEAM_STATE.JUMPER, icon: 'running' },
        { tooltip: 'filterRequested', state: PROFILE_STORE_TEAM_STATE.REQUESTED, icon: 'fas fa-fw fa-question-circle' },
      ],
      currentPage: 1,
      perPage: 10,
      storeData: [],
      filterName: '',
      filterRegionId: null,
      filterCooperationState: null,
      filterMemberState: null,
    }
  },
  computed: {
    filteredStores () {
      const regionFilter = this.filterRegionId
      const nameFilter = this.filterName
      const stateCooperationFilter = this.filterCooperationState
      const memberStateFilter = this.filterMemberState

      const cooperationStatusOrder = [
        COOPERATION_STATUS.COOPERATION_ESTABLISHED,
        COOPERATION_STATUS.IN_NEGOTIATION,
        COOPERATION_STATUS.NO_CONTACT,
        COOPERATION_STATUS.UNCLEAR,
        COOPERATION_STATUS.GIVES_TO_OTHER_CHARITY,
        COOPERATION_STATUS.DOES_NOT_WANT_TO_WORK_WITH_US,
        COOPERATION_STATUS.PERMANENTLY_CLOSED,
      ]

      const memberStatusOrder = [
        PROFILE_STORE_TEAM_STATE.MANAGE_ROLE,
        PROFILE_STORE_TEAM_STATE.ACTIVE,
        PROFILE_STORE_TEAM_STATE.JUMPER,
        PROFILE_STORE_TEAM_STATE.REQUESTED,
      ]

      const compareFunction = (a, b, directionDesc) => {
        const direction = directionDesc ? -1 : 1

        if (a.cooperationStatus !== b.cooperationStatus) {
          return direction * (cooperationStatusOrder.indexOf(a.cooperationStatus) - cooperationStatusOrder.indexOf(b.cooperationStatus))
        }

        if (a.active !== b.active) {
          return direction * (memberStatusOrder.indexOf(a.active) - memberStatusOrder.indexOf(b.active))
        }

        return 0
      }

      return this.storeData.filter(store => {
        const regionMatch = !regionFilter || store.regionId === regionFilter
        const nameMatch = !nameFilter || store.name.toLowerCase().includes(nameFilter.toLowerCase())
        const stateMatch = !stateCooperationFilter || store.cooperationStatus === stateCooperationFilter
        const memberStateMatch = memberStateFilter === null || store.active === memberStateFilter
        return regionMatch && nameMatch && stateMatch && memberStateMatch
      }).sort((a, b) => compareFunction(a, b, false))
    },

    filteredStoresAndPagination () {
      const startIndex = (this.currentPage - 1) * this.perPage
      const endIndex = startIndex + this.perPage
      return this.filteredStores.slice(startIndex, endIndex)
    },
  },
  mounted () {
    const isManager = 1
    this.storeData = this.stores.map(store => ({
      ...store,
      active: store.isManager === isManager ? PROFILE_STORE_TEAM_STATE.MANAGE_ROLE : store.active,
    }))
  },
  methods: {
    setMemberState (button) {
      this.filterMemberState = button.state
      this.currentPage = 1
    },
    resetFilter () {
      this.filterName = null
      this.filterRegionId = null
      this.filterCooperationState = null
      this.filterMemberState = null
      this.currentPage = 1
    },
    filterToRegion (regionId) {
      this.filterRegionId = regionId
      this.currentPage = 1
    },
    filterToState (state) {
      this.filterCooperationState = state
      this.currentPage = 1
    },
    getIconAndTooltip (store) {
      let iconClass = 'fas fa-fw fa-question-circle'
      let tooltipText = this.$i18n('store.appliedFor')

      if (store.active === PROFILE_STORE_TEAM_STATE.MANAGE_ROLE) {
        iconClass = 'fas fa-user-cog'
        tooltipText = this.$i18n('store.isManager')
      } else if (store.active === PROFILE_STORE_TEAM_STATE.JUMPER) {
        iconClass = 'fas fa-running'
        tooltipText = this.$i18n('store.isJumper')
      } else if (store.active === PROFILE_STORE_TEAM_STATE.ACTIVE) {
        iconClass = 'fas fa-user'
        tooltipText = this.$i18n('store.member')
      }

      return { iconClass, tooltipText }
    },
  },
}
</script>

<style lang="scss" scoped>

</style>
