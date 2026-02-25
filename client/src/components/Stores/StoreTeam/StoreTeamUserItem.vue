<template>
  <div
    :id="`user-${user.id}`"
    class="list-group-item p-2 d-flex store-member"
    :class="{ manager: user.isManager }"
  >
    <StoreTeamAvatar :user="user" />
    <div class="flex-grow-1 px-1 small">
      <b>{{ user.name }}</b><br>
      <div v-if="user.phoneNumber">
        <span>{{ user.phoneNumber }}</span><br>
      </div>
      <Time
        v-if="(user.lastPickup ?? user.joinDate) && (!sortingFunction || sortingFunction.displayInfo === 'times')"
        :tooltip="timeTooltip(user)"
        :time="user.lastPickup ?? user.joinDate"
        :muted="false"
        :date-only="true"
        :icon="user.lastPickup ? 'fa-solid fa-fw fa-shopping-cart' : 'fa-solid fa-fw fa-user-plus'"
      />
      <small v-if="hasMemberDistances && sortingFunction && sortingFunction.displayInfo === 'distance'" class="d-block">
        <i class="fas" :class="user.distanceInKm < 0 ? 'fa-exclamation-triangle' : 'fa-diamond-turn-right'" />
        {{ formatDistance(user.distanceInKm) }}
      </small>
    </div>
    <PhoneButton
      v-if="viewIsMobile && user.phoneNumberIsValid"
      class="d-inline m-auto text-nowrap optional-action-button"
      :phone-number="user.phoneNumber"
      variant="outline-secondary"
    />
    <b-button
      v-else-if="user.id !== fsId"
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

<script>
import StoreTeamAvatar from '@/components/Stores/StoreTeam/StoreTeamAvatar.vue'
import PhoneButton from '@/components/PhoneButton.vue'
import Time from '@/components/Time.vue'
import OverflowMenu from '@/components/OverflowMenu.vue'
import { chat } from '@/script'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: { StoreTeamAvatar, PhoneButton, Time, OverflowMenu },
  mixins: [MediaQueryMixin],
  props: {
    user: { type: Object, required: true },
    fsId: { type: Number, required: true },
    mayEditStore: { type: Boolean, default: false },
    sortingFunction: { type: Object, default: null },
    hasMemberDistances: { type: Boolean, default: false },
  },
  methods: {
    chat,
    timeTooltip (user) {
      const title = ['joinDate', 'lastPickup']
        .filter(key => user[key])
        .map(key => this.$t(`store.${key}`, { date: this.$dateFormatter.dateBasic(user[key]) }))
        .join('<br>')
      return { title, html: true, customClass: 'small', placement: 'bottom' }
    },
    formatDistance (distance) {
      if (distance === -1) return this.$t('store.request.distance_short.unknown')
      if (distance === 0) return this.$t('store.request.distance_short.close')
      return this.$t('store.request.distance_short.normal', { distance })
    },
    overflowMenuOptions (user) {
      return [
        { hide: user.id === this.fsId, icon: 'comment', textKey: 'chat.open_chat', callback: () => chat(user.id) },
        { hide: !this.mayEditStore || user.id === this.fsId, icon: 'comments', textKey: 'chat.open_multi_chat', callback: () => this.$emit('multi-chat', user.id) },
        { hide: !user.phoneNumberIsValid, icon: 'phone', textKey: 'pickup.call', href: this.$url('phone_number', user.phoneNumber, true) },
        { hide: !user.phoneNumberIsValid, icon: 'clone', textKey: 'pickup.copyNumber', callback: () => this.$emit('copy-phone', user.phoneNumber) },
        { icon: 'user', textKey: 'profile.go', href: this.$url('profile', user.id) },
        { hide: !this.mayEditStore || user.isActive, icon: 'clipboard-check', textKey: 'store.sm.makeRegularTeamMember', callback: () => this.$emit('toggle-standby', user) },
        { hide: !this.mayEditStore || !user.isActive || user.isManager, icon: 'people-carry', textKey: 'store.sm.makeJumper', callback: () => this.$emit('toggle-standby', user) },
        { hide: !this.mayEditStore || !this.mayBecomeManager(user), icon: 'cog', textKey: 'store.sm.promoteToManager', callback: () => this.$emit('promote', user) },
        { hide: !this.mayEditStore || !user.isManager, icon: 'cog', textKey: 'store.sm.demoteAsManager', callback: () => this.$emit('demote', user) },
        { hide: !this.mayRemoveFromStore(user), icon: 'user-times', textKey: 'store.sm.removeFromTeam', callback: () => this.$emit('remove', user) },
      ]
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
</style>
