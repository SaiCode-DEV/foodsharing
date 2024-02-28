<template>
  <div>
    <Container
      :title="$i18n('pickup.dates')"
      tag="pickup_list"
    >
      <div class="text-right mt-2">
        <button
          v-if="(isCoordinator || mayEditStore)"
          v-b-tooltip
          :title="$i18n('pickup.add_onetime_pickup')"
          class="btn btn-primary btn-sm"
          @click="$bvModal.show('AddPickupModal')"
        >
          <i class="fas fa-plus" />
        </button>
        <button
          v-if="(isCoordinator || mayEditStore)"
          v-b-tooltip
          :title="$i18n('store.delete_date')"
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
          {{ $i18n('pickup.no_slots_available') }}
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
            class="pickup-block"
            @leave="leave"
            @kick="kick"
            @join="join"
            @confirm="confirm"
            @delete="setSlots(pickup.date, 0, pickup.description)"
            @add-slot="setSlots(pickup.date, pickup.totalSlots + 1, pickup.description)"
            @remove-slot="setSlots(pickup.date, pickup.totalSlots - 1, pickup.description)"
            @team-message="sendTeamMessage"
            @edit-description="editDescription"
          />
        </div>
      </div>
    </Container>
    <AddPickupModal :store-id="storeId" />
    <DeletePickupModal :store-id="storeId" />
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
import DataUser from '@/stores/user'
import { pulseError, pulseSuccess } from '@/script'
import PickupsData from '@/stores/pickups'

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
    mayDoPickup: {
      type: Boolean,
      default: null,
    },
    mayEditStore: {
      type: Boolean,
      default: null,
    },
  },
  data () {
    return {
      isLoading: false,
      isModalOpen: false,
      user: DataUser.getters.getUser(),
      interval: null,
    }
  },
  computed: {
    pickups () {
      return PickupsData.getters.getPickups()
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
      if (this.mayDoPickup) {
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
      try {
        await PickupsData.mutations.loadPickups(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_loadingPickup') + e)
      }

      if (!silent) this.isLoading = false
    },
    async join (date) {
      this.isLoading = true
      try {
        await joinPickup(this.storeId, date, DataUser.getters.getUserId())
      } catch (e) {
        console.error(e)
        pulseError(this.$i18n('pickuplist.tooslow') + '<br /><br />' + this.$i18n('pickuplist.tryagain'))
      }
      await this.tryLoadPickups()
    },
    async leave (date) {
      this.isLoading = true
      try {
        await leavePickup(this.storeId, date, DataUser.getters.getUserId())
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_leave') + e)
      }
      await this.tryLoadPickups()
    },
    async kick (data) {
      this.isLoading = true
      try {
        await leavePickup(this.storeId, data.date, data.fsId, data.message)
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_kick') + e)
      }
      await this.tryLoadPickups()
    },
    async confirm (data) {
      this.isLoading = true
      try {
        await confirmPickup(this.storeId, data.date, data.fsId)
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_confirm') + e)
      }
      await this.tryLoadPickups()
    },
    async setSlots (date, totalSlots, description) {
      this.isLoading = true
      try {
        await setPickupSlots(this.storeId, date, totalSlots, description)
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_changeSlotCount') + e)
      }
      await this.tryLoadPickups()
    },
    async sendTeamMessage (msg) {
      try {
        await sendMessage(this.teamConversationId, msg)
        pulseSuccess(this.$i18n('pickup.team_message_success'))
      } catch (e) {
        console.error(e)
        pulseError(this.$i18n('pickuplist.error_whileSending'))
      }
    },
    async editDescription (date, totalSlots, description) {
      this.isLoading = true
      try {
        await setPickupSlots(this.storeId, date, totalSlots, description)
      } catch (e) {
        pulseError(this.$i18n('pickuplist.error_changeSlotCount') + e)
      }
      await this.tryLoadPickups()
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
