<template>
  <div>
    <Container
      :title="title"
      :tag="`store-team-${storeId}`"
      class="store-team"
    >
      <template v-if="loaded">
        <StoreTeamManagementPanel
          v-if="mayEditStore && loaded"
          v-bind="{ storeId, regionId, team, storeTitle }"
          :sorting-function-name="sortingFunction.name"
          @toggle-sorting="toggleSortingFunction"
        />

        <StoreTeamFilterPanel
          :team="foodsaver"
          :filter-function.sync="filterFunction"
        />

        <PaginatedContent
          :items="teamList"
          :page-size="paginateAmount"
          :threshold="5"
        >
          <template #default="{ currentPageItems }">
            <StoreTeamUserItem
              v-for="user of currentPageItems"
              :key="user.id"
              :user="user"
              :fs-id="fsId"
              :may-edit-store="mayEditStore"
              :sorting-function="sortingFunction"
              :has-member-distances="hasMemberDistances"
              @multi-chat="$emit('multi-chat', $event)"
              @copy-phone="copyToClipboard"
              @toggle-standby="toggleStandbyState"
              @promote="promoteToManager"
              @demote="demoteAsManager"
              @remove="removeFromTeam"
              @chat="chat"
            />
          </template>
        </PaginatedContent>
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
    <RequiredMessageModal
      v-if="mayEditStore"
      ref="requiredMessageModal"
      :message-key="messageModalKey"
      :initial-params="{ storeId, store: storeTitle }"
    >
      <b-alert show class="my-3">
        <i class="fas fa-save mr-2" />
        {{ $t('store.log.message_saved_info') }}
      </b-alert>
    </RequiredMessageModal>
  </div>
</template>

<script>
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import {
  demoteAsStoreManager, promoteToStoreManager,
  moveMemberToStandbyTeam, moveMemberToRegularTeam,
  removeStoreMember,
} from '@/api/stores'
import phoneNumber from '@/helper/phone-numbers'
import { pulseError, chat } from '@/script'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import StoreData from '@/stores/stores'
import Container from '@/components/Container/Container.vue'
import PaginatedContent from '@/components/Container/PaginatedContent.vue'
import { HTTP_RESPONSE } from '@/consts'
import StoreTeamUserItem from './StoreTeamUserItem.vue'
import StoreTeamManagementPanel from './StoreTeamManagementPanel.vue'
import StoreTeamFilterPanel from './StoreTeamFilterPanel.vue'
import { usePickupStore } from '@/stores/pickups'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'
import RequiredMessageModal from '@/components/Modals/RequiredMessageModal.vue'

export default {
  components: { Container, PaginatedContent, StoreTeamUserItem, StoreTeamManagementPanel, StoreTeamFilterPanel, RequiredMessageModal },
  mixins: [MediaQueryMixin, CopyToClipboardMixin],
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
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return {
      confirmationDialogue,
      pickupStore: usePickupStore(),
      chat,
    }
  },
  data () {
    return {
      foodsaver: this.team?.map(foodsaver => this.foodsaverData(foodsaver)),
      sortingFunctionIndex: 0,
      filterFunction: { func: () => true },
      defaultAmountForDesktop: 20,
      defaultAmountForMobile: 10,
      messageModalKey: '',
      teamList: [],
    }
  },
  computed: {
    title () {
      if (!this.loaded || !this.filterFunction.name) return this.$t('store.team_container')
      const filterName = this.$t(`store.sm.${this.filterFunction.name}`)
      return `${this.$t('store.team_container')} (${this.filterFunction.count} ${filterName})`
    },
    paginateAmount () {
      return this.viewIsMobile ? this.defaultAmountForMobile : this.defaultAmountForDesktop
    },
    hasMemberDistances () {
      if (!this.loaded) return false
      return Number.isInteger(this.team?.[0]?.distanceInKm)
    },
    sortingFunctions () {
      const sortingFunctions = [
        { func: this.defaultSortingFunction, name: 'default', displayInfo: 'times' },
        { func: this.pickupSortingFunction, name: 'pickup', displayInfo: 'times' },
      ]
      if (this.hasMemberDistances) {
        sortingFunctions.push(
          { func: this.distanceSortingFunction, name: 'distance', displayInfo: 'distance' },
        )
      }
      return sortingFunctions
    },
    sortingFunction () {
      return this.sortingFunctions[this.sortingFunctionIndex]
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
  methods: {
    /**
     * Calculates and sorts the list of users, filtered by buttons and search string, and sets it in the mixin where
     * it can be collapsed or expanded by the "show more" button.
     */
    updateList () {
      const newList = this.foodsaver.filter(this.filterFunction.func)
      newList.sort(this.sortingFunction.func)
      this.teamList = newList
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
        pulseError(this.$t('error_unexpected'))
        console.error(e)
      }
    },
    async promoteToManager (user) {
      try {
        await promoteToStoreManager(this.storeId, user.id)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        if (e.code === HTTP_RESPONSE.UNPROCESSABLE_ENTITY) {
          pulseError(this.$t('store.sm.promoteToManagerNotPossible'))
        } else {
          pulseError(this.$t('error_unexpected'))
        }
      }
    },
    async demoteAsManager (user) {
      let message = ''
      if (user.id === this.fsId) {
        if (!await this.confirmationDialogue('store.sm.demoteAsManagerConfirm', {
          okTitle: this.$t('yes'),
        })) return
      } else {
        this.messageModalKey = 'demote_store_manager'
        await this.$nextTick()
        message = await this.$refs.requiredMessageModal.tryGetMessage({ name: user.firstName })
        if (message === false) return
      }

      try {
        await demoteAsStoreManager(this.storeId, user.id, message)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
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
    distanceSortingFunction (a, b) {
      if (a.isManager !== b.isManager) return b.isManager - a.isManager
      // invalid addresses are sent as distance: -1, but should be sorted to the bottom.
      // 1 Mio km are always sufficient for that.
      return ((a.distanceInKm < 0 ? 1e6 : a.distanceInKm) - (b.distanceInKm < 0 ? 1e6 : b.distanceInKm))
    },
    foodsaverData (fs) {
      const validPhoneNumber = phoneNumber.callableNumber(fs.handy || fs.telefon, true)
      return {
        id: fs.id,
        isActive: fs.membershipStatus === 1, // MembershipStatus::MEMBER
        isJumper: fs.membershipStatus === 2, // MembershipStatus::JUMPER
        isManager: fs.isResponsible,
        isVerified: fs.isVerified,
        mayManage: fs.role >= 2, // Role::STORE_MANAGER
        avatar: fs.avatar,
        isSleeping: fs.isSleeping,
        name: fs.name,
        firstName: fs.firstName,
        phoneNumber: validPhoneNumber,
        phoneNumberIsValid: !!validPhoneNumber,
        joinDate: fs.memberSince ? new Date(fs.memberSince) : null,
        lastPickup: fs.lastFetch ? new Date(fs.lastFetch) : null,
        fetchCount: fs.fetchCount,
        hasHygieneCertificateUntil: fs.hygieneCertificateUntil ? new Date(fs.hygieneCertificateUntil) : null,
        distanceInKm: fs.distanceInKm ?? null,
      }
    },
    async removeFromTeam (user) {
      const pickups = this.pickupStore.getPickups
      const occupiedSlots = pickups.filter(pickup => pickup.occupiedSlots.find(slot => slot.profile.id === user.id))
      const dialogueOptions = {
        params: Object.assign({ occupiedSlots: occupiedSlots.length }, user),
        okTitle: this.$t('button.yes_i_am_sure'),
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
        pulseError(this.$t('error_unexpected'))
      }
    },

    toggleSortingFunction () {
      this.sortingFunctionIndex = (this.sortingFunctionIndex + 1) % this.sortingFunctions.length
      this.updateList()
    },
    timeTooltip (user) {
      const title = ['joinDate', 'lastPickup']
        .filter(key => user[key])
        .map(key => this.$t(`store.${key}`, { date: this.$dateFormatter.dateBasic(user[key]) }))
        .join('<br>')
      return { title, html: true, customClass: 'small', placement: 'bottom' }
    },
  },
}
</script>

<style lang="scss" scoped>
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
