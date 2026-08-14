<template>
  <div>
    <Container
      :title="$t('pickup.slots')"
      :tag="`store-pickup-list-${storeId}`"
      wrap-content="p-0"
    >
      <div
        v-if="userStore.isPassportInvalid"
        class="alert alert-danger m-1"
        role="alert"
      >
        <i class="fas fa-triangle-exclamation" />
        <i class="fas fa-id-card mr-2" />
        {{ $t('store.passport_expired') }}
        <br>
        <router-link
          :to="$url('settingsPassport')"
          class="alert-link mt-1"
        >
          {{ $t('error.passport_is_invalid.link') }}
        </router-link>
      </div>
      <div
        v-else-if="userStore.isPassportInvalidSoon"
        class="alert alert-warning m-1"
        role="alert"
      >
        <i class="fas fa-triangle-exclamation" />
        <i class="fas fa-id-card mr-2" />
        {{ $t('store.passport_expires_soon', { days: userStore.details.lastPassUntilValidInDays }) }}
        <br>
        <router-link
          :to="$url('settingsPassport')"
          class="alert-link mt-1"
        >
          {{ $t('error.passport_is_invalid_soon.link') }}
        </router-link>
      </div>
      <div class="text-right mt-2 pr-2">
        <button
          v-if="(isCoordinator || mayEditStore)"
          v-b-tooltip
          :title="$t('pickup.add_onetime_pickup')"
          class="btn btn-primary btn-sm"
          @click="$bvModal.show('AddPickupModal')"
        >
          <i class="fas fa-plus" />
        </button>
        <button
          v-if="(isCoordinator || mayEditStore)"
          v-b-tooltip
          :title="$t('store.delete_date')"
          class="btn btn-primary btn-sm"
          @click="$bvModal.show('DeletePickupModal')"
        >
          <i class="fas fa-trash-alt" />
        </button>
      </div>
      <div
        :class="{disabledLoading: isLoading}"
        class="pickup-list card-body"
      >
        <div v-if="pickups.length <= 0">
          {{ $t('pickup.no_slots_available') }}
        </div>
        <div v-if="pickups.length">
          <Pickup
            v-for="pickup in pickups"
            :key="pickup.date.valueOf()"
            v-bind="pickup"
            :store-id="storeId"
            :store-title="storeTitle"
            :may-edit-store="mayEditStore"
            :is-coordinator="isCoordinator"
            :user="user"
            :description="pickup.description"
            :passport-still-valid="isPassportValidOn(pickup.date)"
            class="pickup-block"
            @leave="leave"
            @kick="kick"
            @join="join"
            @confirm="confirm"
            @delete="setSlots(pickup.date, 0, pickup.description)"
            @add-slot="setSlots(pickup.date, pickup.totalSlots + 1, pickup.description)"
            @remove-slot="deleteSlot(pickup.date, pickup.totalSlots, pickup.description, $event)"
            @team-message="sendTeamMessage"
            @edit-description="editDescription"
          />
        </div>
      </div>
    </Container>
    <AddPickupModal :store-id="storeId" />
    <DeletePickupModal :store-id="storeId" />
    <b-modal
      id="DeleteLastSlotModal"
      ref="deleteLastSlotModal"
      :title="$t('pickuplist.really_delete_last_slot.title')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.yes_i_am_sure')"
      ok-variant="outline-danger"
      centered
      @ok="confirmDeleteLastSlot"
      @hide="lastSlotDeleteData = null"
    >
      <div>{{ $t('pickuplist.really_delete_last_slot.body') }}</div>
    </b-modal>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { VBTooltip } from 'bootstrap-vue'
import Pickup from '@/components/Stores/Pickup/Pickup.vue'
import AddPickupModal from '@/components/Modals/Store/AddPickupModal.vue'
import DeletePickupModal from '../Modals/Store/DeletePickupModal.vue'
import { setPickupSlots, confirmPickup, joinPickup, leavePickup } from '@/api/pickups'
import { sendMessage } from '@/api/conversations'
import { useUserStore } from '@/stores/user'
import { pulseError, pulseSuccess } from '@/script'
import { usePickupStore } from '@/stores/pickups'

export default {
  components: { Pickup, AddPickupModal, DeletePickupModal, Container },
  directives: { VBTooltip },
  props: {
    storeId: {
      type: Number,
      required: true,
    },
    storeTitle: {
      type: String,
      default: '',
    },
    isCoordinator: {
      type: Boolean,
      default: false,
    },
    teamConversationId: {
      type: Number,
      default: null,
    },
    maySeePickup: {
      type: Boolean,
      default: null,
    },
    mayEditStore: {
      type: Boolean,
      default: null,
    },
    categoryType: {
      type: Number,
      default: () => require('@/constants/storeCategoryTypes').STORE_CATEGORY_PICKUP,
    },
  },
  setup () {
    return {
      userStore: useUserStore(),
      pickupStore: usePickupStore(),
    }
  },
  data () {
    return {
      isLoading: false,
      isModalOpen: false,
      user: this.userStore.getUser,
      interval: null,
      lastSlotDeleteData: null,
    }
  },
  computed: {
    pickups () {
      return this.pickupStore.getPickups
    },
  },
  async created () {
    await this.loadPickups()
  },
  destroyed () {
    clearInterval(this.interval)
  },
  methods: {
    async loadPickups () {
      if (this.maySeePickup) {
        await this.tryLoadPickups()
        // pull for updates every 30 seconds
        this.interval = setInterval(() => {
          this.tryLoadPickups(true) // reload without loading indicator
        }, 30 * 1000)
      } else {
        clearInterval(this.interval)
      }
    },
    async tryLoadPickups (silent = false) {
      if (!silent) this.isLoading = true
      // skip periodic background updates when the tab is hidden,
      // but always run the initial/explicit load
      if (silent && document.hidden) {
        return
      }
      try {
        await this.pickupStore.loadPickups(this.storeId)
      } catch (e) {
        pulseError(this.$t('pickuplist.error_loadingPickup') + e)
      }

      if (!silent) this.isLoading = false
    },
    async join (date) {
      this.isLoading = true
      try {
        await joinPickup(this.storeId, date)
        this.pickupStore.invalidateOptionsCache()
        this.pickupStore.invalidateRegisteredCache()
      } catch (e) {
        console.error(e)
        pulseError(this.$t('pickuplist.tooslow') + '<br /><br />' + this.$t('pickuplist.tryagain'))
      }
      await this.tryLoadPickups()
    },
    async leave (date) {
      this.isLoading = true
      try {
        await leavePickup(this.storeId, date, this.userStore.getUserId)
        this.pickupStore.invalidateOptionsCache()
        this.pickupStore.invalidateRegisteredCache()
      } catch (e) {
        pulseError(this.$t('pickuplist.error_leave') + e)
      }
      await this.tryLoadPickups()
    },
    async kick (data) {
      this.isLoading = true
      try {
        await leavePickup(this.storeId, data.date, data.fsId, data.message)
      } catch (e) {
        pulseError(this.$t('pickuplist.error_kick') + e)
      }
      await this.tryLoadPickups()
    },
    async confirm (data) {
      this.isLoading = true
      try {
        await confirmPickup(this.storeId, data.date, data.fsId)
      } catch (e) {
        pulseError(this.$t('pickuplist.error_confirm') + e)
      }
      await this.tryLoadPickups()
    },
    async setSlots (date, totalSlots, description) {
      this.isLoading = true
      try {
        await setPickupSlots(this.storeId, date, totalSlots, description)
        this.pickupStore.invalidateOptionsCache()
        this.pickupStore.invalidateRegisteredCache()
      } catch (e) {
        pulseError(this.$t('pickuplist.error_changeSlotCount') + e)
      }
      await this.tryLoadPickups()
    },
    async deleteSlot (date, totalSlots, description, confirm) {
      // Ask for confirmation when deleting the last slot via direct click (not
      // via menu)
      if (confirm && totalSlots === 1) {
        this.lastSlotDeleteData = { date, totalSlots, description }
        this.$bvModal.show('DeleteLastSlotModal')
        return
      }
      this.setSlots(date, totalSlots - 1, description)
    },
    confirmDeleteLastSlot () {
      if (this.lastSlotDeleteData) {
        this.setSlots(
          this.lastSlotDeleteData.date,
          this.lastSlotDeleteData.totalSlots - 1,
          this.lastSlotDeleteData.description,
        )
        this.lastSlotDeleteData = null
      }
    },
    async sendTeamMessage (msg) {
      try {
        await sendMessage(this.teamConversationId, msg)
        pulseSuccess(this.$t('pickup.team_message_success'))
      } catch (e) {
        console.error(e)
        pulseError(this.$t('pickuplist.error_whileSending'))
      }
    },
    async editDescription (date, totalSlots, description) {
      this.isLoading = true
      try {
        await setPickupSlots(this.storeId, date, totalSlots, description)
        this.pickupStore.invalidateOptionsCache()
        this.pickupStore.invalidateRegisteredCache()
      } catch (e) {
        pulseError(this.$t('pickuplist.error_changeSlotCount') + e)
      }
      await this.tryLoadPickups()
    },
    isPassportValidOn (date) {
      if (!this.userStore.isLoadingFinished) return null
      if (this.userStore.details?.lastPassUntilValid === null) return false
      return date < new Date(this.userStore.details.lastPassUntilValid)
    },
  },
}
</script>

<style lang="scss" scoped>
.pickup-list {
  padding: 10px;

  .pickup-block:last-child {
    margin-bottom: -10px;
  }
}

.btn-group.slot-actions {
  // counter the .card definition of padding: 6px 8px;
  margin: -6px -8px;

  button {
    line-height: 21px;
    padding: 5px 10px;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
  }

  i.fas {
    font-size: 14px;
  }
}
</style>
