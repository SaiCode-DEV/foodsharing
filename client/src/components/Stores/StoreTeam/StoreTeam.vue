<template>
  <!--
  Default team list order, sorted by number of pickups each:
  - store managers
  - active team members
  - unverified team members
  - jumpers
  Sleeping team members will come last in each of those sections.
  Check `tableSortFunction` (and StoreGateway:getStoreTeam) for details.
  -->
  <div>
    <Container
      :title="title"
      :toggle-visiblity="list.length > defaultAmount"
      tag="store_team"
      class="store-team"
      @show-full-list="showFullList"
      @reduce-list="reduceList"
    >
      <div class="text-center mt-2 mb-2">
        <button
          v-if="(isCoordinator || mayEditStore)"
          v-b-tooltip.hover.top
          :title="$i18n(managementModeEnabled ? 'store.sm.managementToggleOff' : 'store.sm.managementToggleOn')"
          class="btn btn-primary btn-sm"
          href="#"
          @click.prevent="toggleManageControls"
        >
          {{ $i18n('store.sm.buttonManagementToggle') }}
        </button>
        <button
          v-if="applications.storeRequests && applications.storeRequests.length > 0"
          class="btn btn-danger btn-sm"
          href="#"
          @click="$bvModal.show('requests')"
        >
          {{ $i18n('store.requests', { count: applications.storeRequests.length}) }}
        </button>
      </div>

      <div class="text-center mb-2">
        <template v-for="(filterButton, index) in updatedFilterButtons">
          <br v-if="index === 3" :key="index">
          <b-button
            v-if="(filterButton.manageMode && managementModeEnabled) || filterButton.manageMode === false"
            :key="filterButton.key"
            v-b-tooltip.hover.bottom="filterButton.tooltip"
            class="mr-2 mb-1"
            size="sm"
            :variant="getFilterButtonClass(isFilterActive(filterButton.state))"
            @click="applyFilter(filterButton.state)"
          >
            {{ filterButton.count }} <i :class="filterButton.icon" />
          </b-button>
        </template>
      </div>

      <div class="search-container">
        <input
          v-model="userSearchString"
          type="text"
          class="form-control-sm"
          :placeholder="$i18n('store.team.search_input')"
          @input="updateList"
        >
        <b-button
          v-b-tooltip.hover.top
          variant="outline-secondary"
          size="sm"
          :title="$i18n('store.team.search_reset')"
          @click="resetUserSearchString"
        >
          <i class="fas fa-times" />
        </b-button>
      </div>
      <!-- preparation for more store-management features -->
      <StoreManagementPanel
        v-if="managementModeEnabled"
        :store-id="storeId"
        :team="team"
        classes="pt-2"
        :region-id="regionId"
      />

      <div class="card-body team-list">
        <b-table
          ref="teamlist"
          :items="filteredList"
          :fields="tableFields"
          details-td-class="col-actions"
          primary-key="id"
          thead-class="d-none"
          sort-by="ava"
          :busy="isBusy"
          :empty-text="$i18n('store.team.no_record')"
          show-empty
          sort-null-last
        >
          <template #cell(ava)="data">
            <StoreTeamAvatar :user="data.item" />
          </template>

          <template #cell(info)="data">
            <StoreTeamInfo
              :user="data.item"
              :store-manager-view="managementModeEnabled"
              @toggle-details="toggleActions(data)"
            />
          </template>

          <template #cell(mobinfo)="data">
            <StoreTeamInfotext
              :member="data.item"
              :is-coordinator="isCoordinator"
              :may-edit-store="mayEditStore"
            />
          </template>

          <template #cell(call)="data">
            <div v-if="data.item.phoneNumberIsValid">
              <b-button
                variant="link"
                class="member-call"
                :href="$url('phone_number', data.item.phoneNumber,true)"
                :disabled="!data.item.phoneNumberIsValid"
              >
                <i class="fas fa-fw fa-phone" />
              </b-button>
              <b-button
                variant="link"
                class="member-call copy-clipboard"
                href="#"
                @click.prevent="copyIntoClipboard(data.item.phoneNumber)"
              >
                <i class="fas fa-fw fa-clone" />
              </b-button>
            </div>
          </template>

          <template #row-details="data">
            <StoreTeamInfotext
              v-if="wXS"
              :member="data.item"
              :is-coordinator="isCoordinator"
              :may-edit-store="mayEditStore"
              classes="text-center"
            />

            <div class="member-actions">
              <b-button
                v-if="(wXS || wSM)"
                size="sm"
                :href="`/profile/${data.item.id}`"
              >
                <i class="fas fa-fw fa-user" />
                {{ $i18n('pickup.open_profile') }}
              </b-button>

              <b-button
                v-if="data.item.id !== fsId"
                size="sm"
                variant="secondary"
                :block="!(wXS || wSM)"
                @click="openChat(data.item.id)"
              >
                <i class="fas fa-fw fa-comment" />
                {{ $i18n('chat.open_chat') }}
              </b-button>

              <b-button
                v-if="(isCoordinator || mayEditStore) && data.item.isJumper"
                size="sm"
                variant="primary"
                :block="!(wXS || wSM)"
                @click="toggleStandbyState(data.item.id, false)"
              >
                <i class="fas fa-fw fa-clipboard-check" />
                {{ $i18n('store.sm.makeRegularTeamMember') }}
              </b-button>

              <b-button
                v-if="(isCoordinator || mayEditStore) && data.item.isActive && !data.item.isManager"
                size="sm"
                variant="primary"
                :block="!(wXS || wSM)"
                @click="toggleStandbyState(data.item.id, true)"
              >
                <i class="fas fa-running" />
                {{ $i18n('store.sm.makeJumper') }}
              </b-button>

              <b-button
                v-if="managementModeEnabled && mayBecomeManager(data.item)"
                size="sm"
                variant="warning"
                :block="!(wXS || wSM)"
                @click="promoteToManager(data.item.id)"
              >
                <i class="fas fa-fw fa-cog" />
                {{ $i18n('store.sm.promoteToManager') }}
              </b-button>

              <b-button
                v-if="managementModeEnabled && data.item.isManager"
                size="sm"
                variant="outline-primary"
                :block="!(wXS || wSM)"
                @click="demoteAsManager(data.item.id, data.item.name)"
              >
                <i class="fas fa-fw fa-cog" />
                {{ $i18n('store.sm.demoteAsManager') }}
              </b-button>

              <b-button
                v-if="mayRemoveFromStore(data.item)"
                size="sm"
                variant="danger"
                :block="!(wXS || wSM)"
                @click="openDeleteModal(data.item)"
              >
                <i class="fas fa-fw fa-user-times" />
                {{ $i18n('store.sm.removeFromTeam') }}
              </b-button>
            </div>
          </template>
        </b-table>
      </div>
    </Container>
    <b-modal
      id="deleteModal"
      ref="deleteModal"
      :title="$i18n('store.sm.reallyRemove', { name: selectedDataItem ? selectedDataItem.name : '' })"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      cancel-variant="primary"
      ok-variant="outline-danger"
      @ok="removeFromTeam(selectedDataItem ? selectedDataItem.id : null, selectedDataItem ? selectedDataItem.name : '')"
    >
      {{ $i18n('really_delete') }}
    </b-modal>
    <StoreApplications
      :store-id="storeId"
      :store-title="storeTitle"
      :store-requests="applications.storeRequests"
    />
  </div>
</template>

<script>
import {
  demoteAsStoreManager, promoteToStoreManager,
  moveMemberToStandbyTeam, moveMemberToRegularTeam, removeStoreMember,
} from '@/api/stores'
import phoneNumber from '@/helper/phone-numbers'
import { chat, pulseSuccess, pulseError } from '@/script'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import StoreManagementPanel from '@/components/Stores/StoreTeam/StoreManagementPanel.vue'
import StoreTeamAvatar from '@/components/Stores/StoreTeam/StoreTeamAvatar.vue'
import StoreTeamInfo from '@/components/Stores/StoreTeam/StoreTeamInfo.vue'
import StoreTeamInfotext from '@/components/Stores/StoreTeam/StoreTeamInfotext.vue'
import StoreData, { STORE_TEAM_STATE } from '@/stores/stores'
import Container from '@/components/Container/Container.vue'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'
import StoreApplications from '@/components/Modals/Store/StoreApplications.vue'
import { HTTP_RESPONSE } from '@/consts'

export default {
  components: { StoreManagementPanel, StoreTeamAvatar, StoreTeamInfo, StoreTeamInfotext, Container, StoreApplications },
  mixins: [MediaQueryMixin, ListToggleMixin],
  props: {
    fsId: { type: Number, required: true },
    mayEditStore: { type: Boolean, default: false },
    isCoordinator: { type: Boolean, default: false },
    team: { type: Array, required: true },
    storeId: { type: Number, required: true },
    storeTitle: { type: String, default: '' },
    regionId: { type: Number, required: true },
  },
  data () {
    return {
      foodsaver: this.team?.map(fs => this.foodsaverData(fs)),
      sortfun: this.tableSortFunction,
      managementModeEnabled: false,
      isBusy: false,
      selectedDataItem: null,
      userSearchString: null,
      activeFilter: null,
      defaultAmountForDesktop: 30,
      defaultAmountForMobile: 10,
      filterButtons: [
        {
          key: 'all',
          tooltip: this.$i18n('store.sm.filterAll'),
          state: null,
          count: null,
          icon: 'fas fa-users',
          manageMode: false,
        },
        {
          key: 'active',
          tooltip: this.$i18n('store.sm.filterActive'),
          state: STORE_TEAM_STATE.ACTIVE,
          count: null,
          icon: 'fas fa-user',
          manageMode: false,
        },
        {
          key: 'jumper',
          tooltip: this.$i18n('store.sm.filterJumper'),
          state: STORE_TEAM_STATE.JUMPER,
          count: null,
          icon: 'fas fa-running',
          manageMode: false,
        },
        {
          key: 'sleeping',
          tooltip: this.$i18n('store.sm.filterSleeping'),
          state: STORE_TEAM_STATE.SLEEPING,
          count: null,
          icon: 'fas fa-bed',
          manageMode: true,
        },
        {
          key: 'unverified',
          tooltip: this.$i18n('store.sm.filterUnverified'),
          state: STORE_TEAM_STATE.UNVERIFIED,
          count: null,
          icon: 'fas fa-user-alt-slash',
          manageMode: true,
        },
        {
          key: 'manage_role',
          tooltip: this.$i18n('store.sm.filterManage'),
          state: STORE_TEAM_STATE.MANAGE_ROLE,
          count: null,
          icon: 'fas fa-gem',
          manageMode: true,
        },
      ],
      isReduced: true,
    }
  },
  computed: {
    applications () {
      return StoreData.getters.getStoreApplications()
    },
    updatedFilterButtons () {
      return this.filterButtons.map(filter => {
        if (filter.state === null) {
          return { ...filter, count: this.allMembers }
        } else if (filter.state === STORE_TEAM_STATE.ACTIVE) {
          return { ...filter, count: this.activeMembers }
        } else if (filter.state === STORE_TEAM_STATE.JUMPER) {
          return { ...filter, count: this.jumperCount }
        } else if (filter.state === STORE_TEAM_STATE.UNVERIFIED) {
          return { ...filter, count: this.unverifiedCount }
        } else if (filter.state === STORE_TEAM_STATE.SLEEPING) {
          return { ...filter, count: this.sleepingMembers }
        } else if (filter.state === STORE_TEAM_STATE.MANAGE_ROLE) {
          return { ...filter, count: this.membersHasStoreMangerQuizRole }
        }
        return filter
      })
    },
    isFilterActive () {
      return (value) => this.activeFilter === value
    },
    getFilterButtonClass () {
      return (value) => (value ? 'primary' : 'secondary')
    },
    filteredUsers () {
      let filtered = this.foodsaver ?? []
      if (this.activeFilter === STORE_TEAM_STATE.ACTIVE) {
        filtered = filtered.filter(member => member.isActive)
      } else if (this.activeFilter === STORE_TEAM_STATE.JUMPER) {
        filtered = filtered.filter(member => member.isJumper)
      } else if (this.activeFilter === STORE_TEAM_STATE.UNVERIFIED) {
        filtered = filtered.filter(member => !member.isVerified)
      } else if (this.activeFilter === STORE_TEAM_STATE.MANAGE_ROLE) {
        filtered = filtered.filter(member => member.mayManage)
      } else if (this.activeFilter === STORE_TEAM_STATE.SLEEPING) {
        filtered = filtered.filter(member => member.sleepStatus)
      }
      return filtered
    },
    tableFields () {
      const fields = [
        { key: 'ava', class: 'col-ava', sortable: true },
        { key: 'info', class: 'col-info' },
        { key: 'call', class: 'col-call' },
      ]
      if (this.wSM) {
        fields.push({ key: 'mobinfo', class: 'col-mobinfo' })
      }
      return fields
    },
    jumperCount () {
      return this.foodsaver.filter(member => member.isJumper).length
    },
    unverifiedCount () {
      return this.foodsaver.filter(member => member.isVerified === false).length
    },
    allMembers () {
      return this.activeMembers + this.jumperCount
    },
    activeMembers () {
      return this.foodsaver.filter(member => member.isActive).length
    },
    sleepingMembers () {
      return this.foodsaver.filter(member => member.sleepStatus).length
    },
    membersHasStoreMangerQuizRole () {
      return this.foodsaver.filter(member => member.mayManage).length
    },
    unverifiedText () {
      return this.unverifiedCount === 1 ? this.$i18n('store.unverified_member') : this.$i18n('store.unverified_members')
    },
    activeText () {
      return this.activeMembers === 1 ? this.$i18n('store.one_active') : this.$i18n('store.active')
    },
    activeFilterText () {
      switch (this.activeFilter) {
        case STORE_TEAM_STATE.ACTIVE:
          return this.activeText
        case STORE_TEAM_STATE.JUMPER:
          return this.$i18n('store.jumping')
        case STORE_TEAM_STATE.UNVERIFIED:
          return this.unverifiedText
        case STORE_TEAM_STATE.MANAGE_ROLE:
          return this.$i18n('store.sm.filterManage')
        case STORE_TEAM_STATE.SLEEPING:
          return this.$i18n('store.sm.filterSleeping')
        default:
          return this.$i18n('store.sm.filterAll')
      }
    },
    title () {
      return `${this.$i18n('store.team_container')} (${this.activeFilterText})`
    },
  },
  watch: {
    team () {
      this.foodsaver = this.team?.map(fs => this.foodsaverData(fs))
      this.updateList()
    },
  },
  async mounted () {
    if (this.mayEditStore) {
      await StoreData.mutations.loadStoreApplications(this.storeId)
    }
    this.setDefaultAmountForDesktop(this.defaultAmountForDesktop)
    this.setDefaultAmountForMobile(this.defaultAmountForMobile)
  },
  methods: {
    /**
     * Calculates and sorts the list of users, filtered by buttons and search string, and sets it in the mixin where
     * it can be collapsed or expanded by the "show more" button.
     */
    updateList () {
      // filter by selected button
      let newList = this.filteredUsers

      // filter by name
      if (this.userSearchString !== null) {
        const searchString = this.userSearchString.trim().toLowerCase()
        newList = newList.filter(member => {
          return (
            member.name.toLowerCase().includes(searchString) ||
            (member.phoneNumberIsValid && member.phoneNumber.includes(searchString))
          )
        })
      }

      // sort
      newList = newList.sort((a, b) => {
        return this.sortfun(a, b)
      })

      this.setList(newList)
    },
    applyFilter (state) {
      this.activeFilter = state
      this.updateList()
    },
    resetUserSearchString () {
      this.userSearchString = null
      this.updateList()
    },
    toggleManageControls () {
      this.sortfun = this.managementModeEnabled ? this.tableSortFunction : this.pickupSortFunction
      this.managementModeEnabled = !this.managementModeEnabled
      this.updateList()
    },
    canCopy () {
      return !!navigator.clipboard
    },
    copyIntoClipboard (text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          pulseSuccess(this.$i18n('pickup.copiedNumber', { number: text }))
        })
      }
    },
    mayRemoveFromStore (user) {
      if (user.isManager) return false
      if (user.id === this.fsId) return true
      return this.mayEditStore
    },
    openDeleteModal (dataItem) {
      this.$refs.deleteModal.show(dataItem)
    },
    mayBecomeManager (user) {
      if (!user.mayManage) return false
      if (user.isJumper) return false
      return !user.isManager
    },
    toggleActions (row) {
      const wasOpen = row.detailsShowing
      this.foodsaver.forEach((item) => {
        if (item._showDetails) {
          // Firefox has some funny ideas about focus handling, so we must all suffer
          this.$root.$emit('bv::hide::tooltip', 'member-' + item.id)
          // close previously open action list
          this.$set(item, '_showDetails', false)
        }
      })
      if (!wasOpen) {
        row.toggleDetails()
        this.selectedDataItem = row.item
      }
    },
    openChat (fsId) {
      chat(fsId)
    },
    async toggleStandbyState (fsId, newStatusIsStandby) {
      this.isBusy = true
      try {
        if (newStatusIsStandby) {
          await moveMemberToStandbyTeam(this.storeId, fsId)
        } else {
          await moveMemberToRegularTeam(this.storeId, fsId)
        }
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
        this.isBusy = false
        return
      } finally {
        await StoreData.mutations.loadStoreMember(this.storeId)
      }
      const index = this.team.findIndex(fs => fs.id === fsId)
      if (index >= 0) {
        const fs = this.foodsaver[index]
        fs.isWaiting = newStatusIsStandby
        fs.isJumper = newStatusIsStandby
        fs.isActive = !newStatusIsStandby
        fs._showDetails = false
        this.foodsaver[index] = fs
      }
      this.isBusy = false
    },
    async promoteToManager (fsId) {
      if (!fsId) {
        return
      }
      this.isBusy = true
      try {
        await promoteToStoreManager(this.storeId, fsId)
      } catch (e) {
        if (e.code === HTTP_RESPONSE.UNPROCESSABLE_ENTITY) {
          pulseError(this.$i18n('store.sm.promoteToManagerNotPossible'))
        } else {
          pulseError(this.$i18n('error_unexpected'))
        }
        this.isBusy = false
        return
      }
      const index = this.team.findIndex(fs => fs.id === fsId)
      if (index >= 0) {
        const fs = this.foodsaver[index]
        fs.isManager = true
        fs._rowVariant = 'warning'
        fs._showDetails = false
        this.foodsaver[index] = fs
      }
      await StoreData.mutations.loadStoreMember(this.storeId)
      this.isBusy = false
    },
    async demoteAsManager (fsId, fsName) {
      if (!fsId) {
        return
      }
      if (!confirm(this.$i18n('store.sm.reallyDemote', { name: fsName }))) {
        return
      }
      this.isBusy = true
      try {
        await demoteAsStoreManager(this.storeId, fsId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
        this.isBusy = false
        return
      }
      const index = this.team.findIndex(fs => fs.id === fsId)
      if (index >= 0) {
        const fs = this.foodsaver[index]
        fs.isManager = false
        fs._rowVariant = ''
        fs._showDetails = false
        this.foodsaver[index] = fs
      }
      await StoreData.mutations.loadStoreMember(this.storeId)
      this.isBusy = false
    },
    /* eslint-disable brace-style */
    pickupSortFunction (a, b, key, directionDesc) {
      const direction = directionDesc ? 1 : -1
      // ORDER BY
      // isManager (verantwortlich) DESC
      if (a.isManager !== b.isManager) { return direction * (a.isManager - b.isManager) }
      // lastPickup (last_fetch) DESC
      if (a.lastPickup && b.lastPickup) {
        if (a.lastPickup.getTime() === b.lastPickup.getTime()) return 0
        return (a.lastPickup > b.lastPickup ? 1 : -1) * direction
      }
      else if (a.lastPickup) { return direction }
      else if (b.lastPickup) { return -1 * direction }
      // joinDate (add_date) DESC
      if (a.joinDate && b.joinDate) {
        if (a.joinDate.getTime() === b.joinDate.getTime()) return 0
        return (a.joinDate > b.joinDate ? 1 : -1) * direction
      }
      else if (a.joinDate) { return direction }
      else if (b.joinDate) { return -1 * direction }
      // name ASC
      return -1 * direction * a.name.localeCompare(b.name)
    },
    /* eslint-enable brace-style */
    tableSortFunction (a, b, key, directionDesc) {
      const direction = directionDesc ? 1 : -1
      // ORDER BY
      // isManager (verantwortlich) DESC
      if (a.isManager !== b.isManager) { return direction * (a.isManager - b.isManager) }
      // isJumper (team_active == MembershipStatus::JUMPER) ASC
      if (a.isJumper !== b.isJumper) { return -1 * direction * (a.isJumper - b.isJumper) }
      // isVerified (verified == 1) DESC
      if (a.isVerified !== b.isVerified) { return direction * (a.isVerified - b.isVerified) }
      // sleepStatus (sleep_status) ASC
      if (a.sleepStatus !== b.sleepStatus) { return -1 * direction * (a.sleepStatus - b.sleepStatus) }
      // fetchCount (stat_fetchcount) DESC
      if (a.fetchCount !== b.fetchCount) { return direction * (a.fetchCount - b.fetchCount) }
      // lastPickup (last_fetch) DESC
      if (a.lastPickup && b.lastPickup) {
        if (a.lastPickup.getTime() === b.lastPickup.getTime()) return 0
        return (a.lastPickup > b.lastPickup ? 1 : -1) * direction
      }
      // joinDate (add_date) DESC
      if (a.joinDate && b.joinDate) {
        if (a.joinDate.getTime() === b.joinDate.getTime()) return 0
        return (a.joinDate > b.joinDate ? 1 : -1) * direction
      }
      // name ASC
      return -1 * direction * a.name.localeCompare(b.name)
    },
    foodsaverData (fs) {
      if (!fs) {
        return {}
      }
      const validPhoneNumber = phoneNumber.callableNumber(fs.handy || fs.telefon, true)
      return {
        id: fs.id,
        isActive: fs.team_active === 1, // MembershipStatus::MEMBER
        isJumper: fs.team_active === 2, // MembershipStatus::JUMPER
        isManager: !!fs.verantwortlich,
        _rowVariant: fs.verantwortlich ? 'warning' : '',
        isVerified: fs.verified === 1,
        mayManage: fs.quiz_rolle >= 2, // Role::STORE_MANAGER
        // mayAmb: fs.quiz_rolle >= 3, // Role::AMBASSADOR
        avatar: fs.photo,
        isWaiting: fs.team_active === 2 || fs.verified < 1, // MembershipStatus::JUMPER or unverified
        sleepStatus: fs.sleep_status,
        name: fs.name,
        phoneNumber: validPhoneNumber,
        phoneNumberIsValid: !!validPhoneNumber,
        joinDate: fs.add_date ? new Date(fs.add_date * 1000) : null, // unix time
        lastPickup: fs.last_fetch ? new Date(fs.last_fetch * 1000) : null, // unix time
        fetchCount: fs.stat_fetchcount,
      }
    },
    async removeFromTeam (fsId) {
      if (!fsId) {
        return
      }
      this.isBusy = true
      try {
        await removeStoreMember(this.storeId, fsId)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
        this.isBusy = false
        return
      }
      this.isBusy = false
    },
  },
}
</script>

<style lang="scss" scoped>
.search-container {
  display: flex;
  align-items: center;
}

.search-container input {
  width: 100%;
}

.store-team .team-list {
  padding: 0;
}

.store-team ::v-deep table {
  display: flex;
  flex-direction: row;
  margin-bottom: 0;

  thead, tbody {
    width: 100%;

    tr {
      display: flex;
      border-bottom: 1px solid var(--fs-border-default);

      &.b-table-details {
        justify-content: center;
      }

      &.table-warning {
        border-bottom-width: 2px;
        border-bottom-color: var(--fs-color-warning-500);
        padding-bottom: 1px;
      }

      &:last-child,
      &.b-table-has-details {
        border-bottom-width: 0;
      }

      td {
        border-top: 0;
      }
    }
  }

  tr td {
    padding: 3px;
    border-top-color: var(--fs-border-default);
    vertical-align: middle;
    cursor: default;
    display: inline-block;

    &.col-actions {
      padding: 0;
    }

    &.col-ava {
      position: relative;
      align-self: center;
    }

    &.col-info {
      flex-grow: 1;
    }

    &.col-mobinfo {
      padding: 0 10px;
      text-align: right;
    }

    &.col-call {
      .member-call {
        padding: 10px;
        align-self: center;
        color: var(--fs-color-secondary-500);

        &.copy-clipboard { opacity: 0.7; }

        &:hover {
          background-color: var(--fs-color-secondary-500);
          color: var(--fs-color-light);
        }
        &:focus {
          outline: 2px solid var(--fs-color-secondary-500);
        }
        &:disabled {
          color: var(--fs-color-primary-300);
        }
      }
    }

    .member-actions {
      padding: 5px 0;

      .btn {
        margin-bottom: 5px;
      }
    }
  }
}
</style>
