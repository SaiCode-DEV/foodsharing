<template>
  <div>
    <b-modal
      ref="takenSlotModal"
      :title="modalTitle"
    >
      <b-row>
        <b-col cols="5" class="text-center">
          <Avatar :user="profile" :size="130" />

          <div class="my-2">
            <b>{{ profile.name }}</b><br>
            <small>{{ phoneNumber }}</small>
          </div>

          <b-button
            v-b-tooltip="$t('profile.go')"
            variant="outline-primary"
            :to="$url('profile', profile.id)"
            size="sm"
          >
            <i class="fas fa-fw fa-user" :aria-label="$t('profile.go')" />
          </b-button>

          <b-button
            v-if="allowChat && !isMe"
            v-b-tooltip="$t('chat.open_chat')"
            variant="outline-primary"
            size="sm"
            @click="openChat"
          >
            <i class="fas fa-fw fa-comment" :aria-label="$t('chat.open_chat')" />
          </b-button>

          <b-button
            v-if="phoneNumber && !isMe"
            v-b-tooltip="$t('pickup.call')"
            :href="$url('phone_number', phoneNumber)"
            size="sm"
            variant="outline-primary"
          >
            <i class="fas fa-fw fa-phone" :aria-label="$t('pickup.call')" />
          </b-button>

          <b-button
            v-if="phoneNumber && !isMe && canCopy"
            v-b-tooltip="$t('pickup.copyNumber')"
            variant="outline-primary"
            size="sm"
            @click="copyToClipboard(phoneNumber)"
          >
            <i class="fas fa-fw fa-clone" :aria-label="$t('pickup.copyNumber')" />
          </b-button>
        </b-col>

        <b-col cols="7">
          <p>
            <b>{{ $t('store.slot_state') }}:</b><br>
            {{ isConfirmedText }}
          </p>
          <p>
            <b>{{ $t('store.signInDateTime') }}</b>:<br>
            {{ signUpPerformedAtDateFormatted }}
          </p>
          <p>
            <b>{{ $t('terminology.previous_pickups') }}:</b> {{ pickupsCount }}
          </p>
          <p>
            <b>{{ $t('store.lastPickupTitle') }}:</b><br>
            {{ getLastFetchDate }}
          </p>

          <details v-if="userOccupiedSlots.length">
            <summary role="button occupied-slot-details-button">
              <b>{{ $t('store.slotsCurrentlyOccupied') }}:</b> {{ userOccupiedSlots.length }}
            </summary>

            <ul class="pl-2">
              <li
                v-for="slot of userOccupiedSlots"
                :key="slot.date.getTime()"
                role="listitem user-occupied-slots-listitem"
                class="m-0"
              >
                <span :title="$dateFormatter.date(slot.date, { type: 'full' })">
                  {{ $dateFormatter.date(slot.date, { short: true }) }}
                </span>

                &ndash;

                <span v-if="slot.isConfirmed">{{ $t('pickup.overview.status.confirmed') }}</span>
                <span v-else>{{ $t('pickup.overview.status.pending') }}</span>
              </li>
            </ul>
          </details>

          <p v-if="!userOccupiedSlots.length">
            <b>{{ $t('store.slotsCurrentlyOccupied') }}:</b> {{ $t('terminology.no_pickups') }}
          </p>
        </b-col>
      </b-row>

      <template #modal-footer="{ hide }">
        <b-button
          size="sm"
          @click="hide()"
        >
          {{ $t('globals.close') }}
        </b-button>
        <b-button
          v-if="allowKick || allowLeave"
          size="sm"
          variant="danger"
          @click="removeFromSlot"
        >
          {{ $t('pickup.kick') }}
        </b-button>
        <b-button
          v-if="allowConfirm && !confirmed"
          size="sm"
          variant="success"
          @click="confirmSlot()"
        >
          {{ $t('pickup.confirm') }}
        </b-button>
      </template>
    </b-modal>

    <Avatar
      :user="profile"
      :size="50"
      style="margin: 2px 2px 2px 1px; cursor: pointer;"
      href=""
      class="taken-slot-dialog-button"
      badge-size="100%"
      :badge-variant="confirmed ? 'success' : 'danger'"
      :options="{ badgeOffset: '-5px' }"
      :transparent="!confirmed"
      @click="openModal"
    >
      <template #badge>
        <i :class="{'fas': true, 'fa-clock': !confirmed, 'fa-check-circle': confirmed}" />
      </template>
    </Avatar>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import PhoneNumbers from '@/helper/phone-numbers'
import conversationStore from '@/stores/conversations'
import { useUserStore } from '@/stores/user'
import StoreData from '@/stores/stores'

import { v4 as uuidv4 } from 'uuid'
import { usePickupStore } from '@/stores/pickups'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin'

export default {
  components: { Avatar },
  mixins: [CopyToClipboardMixin],
  props: {
    date: {
      type: Date,
      required: true,
    },
    signUpDate: {
      type: Date,
      default: null,
    },
    profile: {
      type: Object,
      default: null,
    },
    confirmed: {
      type: Boolean,
      default: false,
    },
    allowLeave: {
      type: Boolean,
      default: false,
    },
    allowKick: {
      type: Boolean,
      default: false,
    },
    allowConfirm: {
      type: Boolean,
      default: false,
    },
    allowChat: {
      type: Boolean,
      default: false,
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
      uniqueId: null,
    }
  },
  computed: {
    pickups () {
      return this.pickupStore.getPickups
    },
    userOccupiedSlots () {
      return this.pickups.flatMap(pickup => pickup.occupiedSlots
        .filter(slot => slot.profile.id === this.profile.id)
        .map(slot => ({
          date: pickup.date,
          ...slot,
        })),
      )
    },
    isConfirmedText () {
      return this.confirmed ? this.$t('pickup.overview.status.confirmed') : this.$t('pickup.overview.status.pending')
    },
    modalTitle () {
      return this.$dateFormatter.dateTime(this.date, { short: true })
    },
    pickupsCount () {
      const userItem = this.storeMember.find(item => item.id === this.profile.id)
      const pickupsCount = userItem?.fetchCount ?? null

      if (pickupsCount === 0) {
        return this.$t('terminology.no_pickups')
      } else {
        return pickupsCount
      }
    },
    signUpPerformedAtDateFormatted () {
      if (!this.signUpDate) {
        return this.$t('store.unknownDate')
      }
      return this.$dateFormatter.dateTime(this.signUpDate, { short: true })
    },
    storeMember () {
      return StoreData.getters.getStoreMember()
    },
    userId () {
      return this.userStore.getUserId
    },
    isManager () {
      return StoreData.getters.isManager(this.userId)
    },
    getLastFetchDate () {
      const lastFetchDate = this.getLastFetchDateFromUser(this.profile.id)
      const dateFormatterLastFetchDate = this.$dateFormatter.date(lastFetchDate, { short: true })
      return lastFetchDate ? dateFormatterLastFetchDate : this.$t('terminology.no_pickups')
    },
    phoneNumber () {
      return PhoneNumbers.callableNumber(this.profile.mobile || this.profile.landline)
    },
    canCopy () {
      return !!navigator.clipboard
    },
    isMe () {
      return this.userStore.getUserId === this.profile.id
    },
  },
  mounted () {
    this.uniqueId = uuidv4()
  },
  methods: {
    confirmSlot () {
      if (this.confirmed && !this.allowConfirm) {
        return
      }
      this.$emit('confirm', this.profile.id)
      this.$refs.takenSlotModal.hide()
    },
    removeFromSlot () {
      this.isMe ? this.leaveFromSlot() : this.kickFromSlot()
    },
    kickFromSlot () {
      if (this.allowKick) {
        this.$emit('kick', this.profile.id)
      }
      this.$refs.takenSlotModal.hide()
    },
    leaveFromSlot () {
      if (this.allowLeave) {
        this.$emit('leave')
      }
    },
    openModal () {
      this.$refs.takenSlotModal.show()
    },
    getLastFetchDateFromUser (userId) {
      const userItem = this.storeMember.find(item => item.id === userId)
      const lastFetchTimestamp = userItem?.lastFetch ?? null
      return (lastFetchTimestamp !== null) ? Date.parse(lastFetchTimestamp) : null
    },
    openChat () {
      this.$refs.takenSlotModal.hide()
      conversationStore.openChatWithUser(this.profile.id)
    },
  },
}
</script>
