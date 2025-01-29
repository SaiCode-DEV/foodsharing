<template>
  <div>
    <Container
      :title="title"
      :toggle-visiblity="list.length > defaultAmount"
      :tag="`store-team-${storeId}`"
      class="store-team"
      @show-full-list="showFullList"
      @reduce-list="reduceList"
    >
      <template v-if="loaded">
        <StoreTeamManagementPanel
          v-if="mayEditStore"
          v-bind="{ storeId, regionId, team }"
          :sorting-function-name="sortingFunction.name"
          @toggle-sorting="toggleSortingFunction"
        />

        <StoreTeamFilterPanel
          :team="foodsaver"
          :filter-function.sync="filterFunction"
        />

        <div
          v-for="user of filteredList"
          :id="`user-${user.id}`"
          :key="user.id"
          class="list-group-item p-2 d-flex store-member"
          :class="{ manager: user.isManager }"
        >
          <StoreTeamAvatar :user="user" />
          <div class="flex-grow-1 px-1 small">
            <b>{{ user.name }}</b><br>
            <span>{{ user.phoneNumber }}</span><br>
            <Time
              v-if="user.lastPickup ?? user.joinDate"
              :tooltip="timeTooltip(user)"
              :time="user.lastPickup ?? user.joinDate"
              :muted="false"
              :date-only="true"
            /><br>
          </div>
          <PhoneButton
            v-if="isMobile"
            class="d-inline m-auto text-nowrap optional-action-button"
            :phone-number="user.phoneNumber"
            variant="outline-secondary"
          />
          <b-button
            v-else
            variant="outline-secondary"
            class="d-inline m-auto optional-action-button"
            @click="chat(user.id)"
          >
            <i class="fas fa-comment" />
          </b-button>
          <OverflowMenu
            class="d-inline m-auto text-nowrap"
            :options="overflowMenuOptions(user)"
          />
        </div>
      </template>

      <div
        v-for="i of [0,1,2]"
        v-else
        :key="i"
        class="list-group-item p-2 d-flex store-member"
      >
        <b-skeleton type="button" size="50px" />
        <div class="ml-2 flex-grow-1 skeleton-text">
          <b-skeleton width="85%" height="0.8em" />
          <b-skeleton width="65%" height="0.5em" />
          <b-skeleton width="70%" height="0.5em" />
        </div>
      </div>
    </Container>
    <StoreApplications
      :store-id="storeId"
      :store-title="storeTitle"
      :store-requests="applications"
    />
    <RequiredMessageModal
      v-if="mayEditStore"
      ref="requiredMessageModal"
      :message-key="messageModalKey"
      :initial-params="{ storeId, store: storeTitle }"
    >
      <b-alert show class="my-3">
        <i class="fas fa-save mr-2" />
        {{ $i18n('store.log.message_saved_info') }}
      </b-alert>
    </RequiredMessageModal>
  </div>
</template>

<script>
import {
  demoteAsStoreManager, promoteToStoreManager,
  moveMemberToStandbyTeam, moveMemberToRegularTeam,
  removeStoreMember,
} from '@/api/stores'
import phoneNumber from '@/helper/phone-numbers'
import { chat, pulseError } from '@/script'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import StoreTeamAvatar from '@/components/Stores/StoreTeam/StoreTeamAvatar.vue'
import StoreData from '@/stores/stores'
import Container from '@/components/Container/Container.vue'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'
import { HTTP_RESPONSE } from '@/consts'
import PhoneButton from '@/components/PhoneButton.vue'
import Time from '@/components/Time.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import StoreTeamManagementPanel from './StoreTeamManagementPanel.vue'
import StoreTeamFilterPanel from './StoreTeamFilterPanel.vue'
import StoreApplications from '@/components/Modals/Store/StoreApplications.vue'
import PickupsData from '@/stores/pickups'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'
import RequiredMessageModal from '@/components/Modals/RequiredMessageModal.vue'

export default {
  components: { StoreTeamAvatar, Container, PhoneButton, Time, OverflowMenu, StoreTeamManagementPanel, StoreTeamFilterPanel, StoreApplications, RequiredMessageModal },
  mixins: [MediaQueryMixin, ListToggleMixin, ConfirmationDialogue, CopyToClipboardMixin],
  props: {
    fsId: { type: Number, required: true },
    mayEditStore: { type: Boolean, default: false },
    isCoordinator: { type: Boolean, default: false },
    loaded: { type: Boolean, default: false },
    team: { type: Array, required: true },
    storeId: { type: Number, required: true },
    storeTitle: { type: String, default: '' },
    regionId: { type: Number, required: true },
  },
  data () {
    const sortingFunctions = [
      { func: this.defaultSortingFunction, name: 'default' },
      { func: this.pickupSortingFunction, name: 'pickup' },
    ]
    return {
      foodsaver: this.team?.map(foodsaver => this.foodsaverData(foodsaver)),
      sortingFunctions,
      sortingFunction: sortingFunctions[0],
      filterFunction: { func: () => true },
      defaultAmountForDesktop: 20,
      defaultAmountForMobile: 10,
      messageModalKey: '',
    }
  },
  computed: {
    title () {
      if (!this.loaded || !this.filterFunction.name) return this.$i18n('store.team_container')
      const filterName = this.$i18n(`store.sm.${this.filterFunction.name}`)
      return `${this.$i18n('store.team_container')} (${this.filterFunction.count} ${filterName})`
    },
    applications () {
      return StoreData.getters.getStoreApplications()
    },
  },
  watch: {
    filterFunction () {
      this.updateList()
    },
    team () {
      this.foodsaver = this.team?.map(fs => this.foodsaverData(fs))
      this.updateList()
    },
  },
  async mounted () {
    this.setDefaultAmountForDesktop(this.defaultAmountForDesktop)
    this.setDefaultAmountForMobile(this.defaultAmountForMobile)
  },
  methods: {
    chat,
    /**
     * Calculates and sorts the list of users, filtered by buttons and search string, and sets it in the mixin where
     * it can be collapsed or expanded by the "show more" button.
     */
    updateList () {
      const newList = this.foodsaver.filter(this.filterFunction.func)
      newList.sort(this.sortingFunction.func)
      this.setList(newList)
    },
    mayRemoveFromStore (user) {
      if (user.isManager) return false
      if (user.id === this.fsId) return false
      return this.mayEditStore
    },
    mayBecomeManager (user) {
      if (!user.mayManage) return false
      if (user.isJumper) return false
      return !user.isManager
    },
    async toggleStandbyState (user) {
      try {
        if (user.isJumper) {
          await moveMemberToRegularTeam(this.storeId, user.id)
        } else {
          this.messageModalKey = 'move_to_standby_team'
          await this.$nextTick()
          const message = await this.$refs.requiredMessageModal.tryGetMessage({ name: user.firstName })
          if (message === false) return
          await moveMemberToStandbyTeam(this.storeId, user.id, message)
        }
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
        console.error(e)
      }
    },
    async promoteToManager (user) {
      try {
        await promoteToStoreManager(this.storeId, user.id)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        if (e.code === HTTP_RESPONSE.UNPROCESSABLE_ENTITY) {
          pulseError(this.$i18n('store.sm.promoteToManagerNotPossible'))
        } else {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
    },
    async demoteAsManager (user) {
      this.messageModalKey = 'demote_store_manager'
      await this.$nextTick()
      const message = await this.$refs.requiredMessageModal.tryGetMessage({ name: user.firstName })
      if (message === false) return

      try {
        await demoteAsStoreManager(this.storeId, user.id, message)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    pickupSortingFunction (a, b) {
      if (a.isManager !== b.isManager) return b.isManager - a.isManager
      if (a.lastPickup || b.lastPickup) return (b.lastPickup || 0) - (a.lastPickup || 0)
      if (a.joinDate || b.joinDate) return (b.joinDate || 0) - (a.joinDate || 0)
      return a.name.localeCompare(b.name)
    },
    defaultSortingFunction (a, b) {
      if (a.isManager !== b.isManager) return b.isManager - a.isManager
      if (a.isJumper !== b.isJumper) return a.isJumper - b.isJumper
      if (a.isVerified !== b.isVerified) return b.isVerified - a.isVerified
      if (a.isSleeping !== b.isSleeping) return a.isSleeping - b.isSleeping
      if (a.fetchCount !== b.fetchCount) return b.fetchCount - a.fetchCount
      if (a.lastPickup && b.lastPickup) return b.lastPickup - a.lastPickup
      if (a.joinDate && b.joinDate) return b.joinDate - a.joinDate
      return a.name.localeCompare(b.name)
    },
    foodsaverData (fs) {
      const validPhoneNumber = phoneNumber.callableNumber(fs.handy || fs.telefon, true)
      return {
        id: fs.id,
        isActive: fs.team_active === 1, // MembershipStatus::MEMBER
        isJumper: fs.team_active === 2, // MembershipStatus::JUMPER
        isManager: !!fs.verantwortlich,
        isVerified: fs.verified === 1,
        mayManage: fs.rolle >= 2, // Role::STORE_MANAGER
        avatar: fs.photo,
        isSleeping: fs.is_sleeping,
        name: fs.name,
        firstName: fs.firstName,
        phoneNumber: validPhoneNumber,
        phoneNumberIsValid: !!validPhoneNumber,
        joinDate: fs.add_date ? new Date(fs.add_date * 1000) : null, // unix time
        lastPickup: fs.last_fetch ? new Date(fs.last_fetch * 1000) : null, // unix time
        fetchCount: fs.stat_fetchcount,
        hasHygieneCertificateUntil: fs.hygiene_certificate_until ? new Date(fs.hygiene_certificate_until) : null,
      }
    },
    async removeFromTeam (user) {
      const pickups = PickupsData.getters.getPickups()
      const occupiedSlots = pickups.filter(pickup => pickup.occupiedSlots.find(slot => slot.profile.id === user.id))
      const dialogueOptions = {
        params: Object.assign({ occupiedSlots: occupiedSlots.length }, user),
        okTitle: this.$i18n('button.yes_i_am_sure'),
      }
      if (occupiedSlots.length && !await this.confirmationDialogue('store.sm.userHasPickupsWarning', dialogueOptions)) return
      this.messageModalKey = 'kick_from_store_team'
      await this.$nextTick()
      const message = await this.$refs.requiredMessageModal.tryGetMessage({ name: user.firstName })
      if (message === false) return

      try {
        await removeStoreMember(this.storeId, user.id, message)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    overflowMenuOptions (user) {
      return [
        { icon: 'comment', textKey: 'chat.open_chat', callback: () => chat(user.id) },
        { icon: 'phone', textKey: 'pickup.call', href: this.$url('phone_number', user.phoneNumber, true) },
        { icon: 'clone', textKey: 'pickup.copyNumber', callback: () => this.copyToClipboard(user.phoneNumber) },
        { icon: 'user', textKey: 'profile.go', href: this.$url('profile', user.id) },
        { hide: !this.mayEditStore || user.isActive, icon: 'clipboard-check', textKey: 'store.sm.makeRegularTeamMember', callback: () => this.toggleStandbyState(user) },
        { hide: !this.mayEditStore || !user.isActive || user.isManager, icon: 'running', textKey: 'store.sm.makeJumper', callback: () => this.toggleStandbyState(user) },
        { hide: !this.mayEditStore || !this.mayBecomeManager(user), icon: 'cog', textKey: 'store.sm.promoteToManager', callback: () => this.promoteToManager(user) },
        { hide: !this.mayEditStore || !user.isManager, icon: 'cog', textKey: 'store.sm.demoteAsManager', callback: () => this.demoteAsManager(user) },
        { hide: !this.mayRemoveFromStore(user), icon: 'user-times', textKey: 'store.sm.removeFromTeam', callback: () => this.removeFromTeam(user) },
      ]
    },
    toggleSortingFunction () {
      this.sortingFunction = this.sortingFunctions.at(this.sortingFunctions.indexOf(this.sortingFunction) - 1)
      this.updateList()
    },
    timeTooltip (user) {
      const title = ['joinDate', 'lastPickup']
        .filter(key => user[key])
        .map(key => this.$i18n(`store.${key}`, { date: this.$dateFormatter.dateBasic(user[key]) }))
        .join('<br>')
      return { title, html: true, customClass: 'small', placement: 'bottom' }
    },
  },
}
</script>

<style lang="scss" scoped>
.manager {
  background-color: var(--fs-color-warning-200) !important;
}
.manager + div, .manager.store-member {
  border-top-color: var(--fs-color-warning-500);
  border-top-width: 2px !important;
}
.filter-section + .store-member {
  box-shadow: 0px 10px 5px -9px inset #0008;
}
.store-team {
  container-type: inline-size;
}
.skeleton-text {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
}
.optional-action-button {
  display: none !important;
}
@container (min-width: 20em) {
  .optional-action-button {
    display: inline-block !important;
  }
}
</style>
