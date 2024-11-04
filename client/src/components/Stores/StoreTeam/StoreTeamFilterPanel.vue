<template>
  <div class="list-group-item p-2 filter-section">
    <div class="mb-2 d-flex">
      <b-form-input
        v-model="userSearchString"
        size="sm"
        class="mr-2"
        :placeholder="$i18n('store.team.search_input')"
      />
      <b-button
        v-b-tooltip.hover="$i18n('store.team.search_reset')"
        variant="outline-secondary"
        size="sm"
        @click="userSearchString = ''"
      >
        <i class="fas fa-times" />
      </b-button>
    </div>

    <b-button-group class="d-flex">
      <b-button
        v-for="button of filterButtonsWithCount"
        :key="button.state"
        v-b-tooltip.hover="$i18n(`store.sm.${button.tooltip}`)"
        size="sm"
        variant="outline-primary"
        :pressed="button.state === activeFilter.state"
        class="flex-grow-1"
        @click="activeFilter = button"
      >
        <i :class="`fas fa-${button.icon}`" /><br>
        {{ button.count }}
      </b-button>
    </b-button-group>
  </div>
</template>
<script>
import { STORE_TEAM_STATE } from '@/stores/stores'

export default {
  props: {
    team: { type: Array, required: true },
  },
  data () {
    const filterButtons = [
      { tooltip: 'filterAll', state: null, icon: 'users' },
      { tooltip: 'filterActive', state: STORE_TEAM_STATE.ACTIVE, icon: 'user' },
      { tooltip: 'filterJumper', state: STORE_TEAM_STATE.JUMPER, icon: 'running' },
      { tooltip: 'filterSleeping', state: STORE_TEAM_STATE.SLEEPING, icon: 'bed' },
      { tooltip: 'filterUnverified', state: STORE_TEAM_STATE.UNVERIFIED, icon: 'user-alt-slash' },
      { tooltip: 'filterManage', state: STORE_TEAM_STATE.MANAGE_ROLE, icon: 'user-graduate' },
    ]
    return {
      userSearchString: '',
      activeFilter: filterButtons[0],
      filterButtons,
      filters: {
        [null]: () => true,
        [STORE_TEAM_STATE.ACTIVE]: member => member.isActive,
        [STORE_TEAM_STATE.JUMPER]: member => member.isJumper,
        [STORE_TEAM_STATE.UNVERIFIED]: member => !member.isVerified,
        [STORE_TEAM_STATE.MANAGE_ROLE]: member => member.mayManage,
        [STORE_TEAM_STATE.SLEEPING]: member => member.sleepStatus,
        [STORE_TEAM_STATE.HYGIENE]: member => member.hasHygieneCertificateUntil,
      },
    }
  },
  computed: {
    filterButtonsWithCount () {
      return this.filterButtons.map(filter => {
        return {
          ...filter,
          count: this.team.filter(this.filters[filter.state]).length,
        }
      })
    },
    filterFunction () {
      const searchString = this.userSearchString.trim().toLowerCase()
      const filter = this.filters[this.activeFilter.state]
      return {
        name: this.activeFilter.tooltip,
        count: this.team.filter(filter).length,
        func: (member) => filter(member) && (
          member.name.toLowerCase().includes(searchString) ||
          (member.phoneNumberIsValid && member.phoneNumber.includes(searchString))
        ),
      }
    },
  },
  watch: {
    filterFunction: {
      immediate: true,
      handler () {
        this.$emit('update:filter-function', this.filterFunction)
      },
    },
  },
  async mounted () {
    if (await this.$isFeatureToggleActive('hygieneQuiz')) {
      this.filterButtons.push({ tooltip: 'filterHygiene', state: STORE_TEAM_STATE.HYGIENE, icon: 'hands-wash' })
    }
  },
}
</script>
