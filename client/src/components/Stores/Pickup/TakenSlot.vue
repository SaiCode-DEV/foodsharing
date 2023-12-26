<template>
  <div>
    <b-modal
      ref="takenSlotModal"
      :title="modalTitle"
    >
      <b-row>
        <b-col cols="5">
          <Avatar
            :url="profile.avatar"
            :size="130"
          />
          <p>
            <b>{{ profile.name }}</b>
          </p>
        </b-col>
        <b-col cols="7">
          <p>
            <b>{{ $i18n('store.slot_state') }}:</b><br>
            {{ isConfirmedText }}
          </p>
          <p v-if="signUpPerformedAtDateFormatted">
            <b>{{ $i18n('store.signInDateTime') }}</b>:<br>
            {{ signUpPerformedAtDateFormatted }}
          </p>
          <p>
            <b>{{ $i18n('terminology.previous_pickups') }}:</b> {{ pickupsCount }}
          </p>
          <p>
            <b>{{ $i18n('store.lastPickupTitle') }}:</b><br>
            {{ getLastFetchDate }}
          </p>
          <p>
            <b>{{ $i18n('store.slotsCurrentlyOccupied') }}</b>: {{ countUserIdInPickups }}
          </p>
        </b-col>
      </b-row>
      <b-button
        variant="outline-primary"
        :href="`/profile/${profile.id}`"
        size="sm"
        class="mb-2"
      >
        <i class="fas fa-fw fa-user" /> {{ $i18n('profile.go') }}
      </b-button>
      <b-button
        v-if="allowChat && !isMe"
        variant="outline-primary"
        class="mb-2"
        size="sm"
        @click="openChat"
      >
        <i class="fas fa-fw fa-comment" />  {{ $i18n('chat.open_chat') }}
      </b-button>
      <b-button
        v-if="phoneNumber && !isMe"
        :href="$url('phone_number', phoneNumber)"
        class="mb-2"
        size="sm"
        variant="outline-primary"
      >
        <i class="fas fa-fw fa-phone" /> {{ $i18n('pickup.call') }}
      </b-button>
      <b-button
        v-if="phoneNumber && !isMe"
        variant="outline-primary"
        class="mb-2"
        size="sm"
        @click="copyIntoClipboard(phoneNumber)"
      >
        <i
          class="fas fa-fw"
          :class="[canCopy ? 'fa-clone' : 'fa-phone-slash']"
        />
        <span v-if="canCopy">{{ $i18n('pickup.copyNumber') }}</span>
        <span v-else>{{ phoneNumber }}</span>
      </b-button>
      <template #modal-footer="{ hide }">
        <b-button
          size="sm"
          @click="hide()"
        >
          {{ $i18n('globals.close') }}
        </b-button>
        <b-button
          v-if="allowKick || allowLeave"
          size="sm"
          variant="danger"
          @click="removeFromSlot"
        >
          {{ $i18n('pickup.kick') }}
        </b-button>
        <b-button
          v-if="allowConfirm && !confirmed"
          size="sm"
          variant="success"
          @click="confirmSlot()"
        >
          {{ $i18n('pickup.confirm') }}
        </b-button>
      </template>
    </b-modal>

    <b-button
      :id="`slot-${uniqueId}`"
      toggle-class="btn p-0 filled"
      @click="openModal"
    >
      <div class="button-container">
        <Avatar
          :url="profile.avatar"
          :size="50"
          :class="{'pending': !confirmed, 'confirmed': confirmed}"
        />
        <div :class="{'slotstatus': true, 'pending': !confirmed, 'confirmed': confirmed}">
          <i :class="{'slotstatus-icon fas': true, 'fa-clock': !confirmed, 'fa-check-circle': confirmed}" />
        </div>
      </div>
    </b-button>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar'
import { pulseSuccess } from '@/script'
import PhoneNumbers from '@/helper/phone-numbers'
import conversationStore from '@/stores/conversations'
import DataUser from '@/stores/user'
import StoreData, { STORE_LOG_ACTION } from '@/stores/stores'

import { v4 as uuidv4 } from 'uuid'
import PickupsData from '@/stores/pickups'

export default {
  components: { Avatar },
  props: {
    date: {
      type: Date,
      required: true,
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
  data () {
    return {
      uniqueId: null,
    }
  },
  computed: {
    pickups () {
      return PickupsData.getters.getPickups()
    },
    countUserIdInPickups () {
      return this.pickups.reduce((count, pickup) => {
        return count + pickup.occupiedSlots.filter(slot => slot.profile.id === this.profile.id).length
      }, 0)
    },
    isConfirmedText () {
      return this.confirmed ? this.$i18n('pickup.overview.status.confirmed') : this.$i18n('pickup.overview.status.pending')
    },
    modalTitle () {
      return this.$dateFormatter.dateTime(this.date, { short: true })
    },
    pickupsCount () {
      const userItem = this.storeMember.find(item => item.id === this.profile.id)
      const pickupsCount = userItem?.stat_fetchcount ?? null

      if (pickupsCount === 0) {
        return this.$i18n('terminology.no_pickups')
      } else {
        return pickupsCount
      }
    },
    signUpPerformedAtDateFormatted () {
      const storeLog = StoreData.getters.getFilteredStoreLog([STORE_LOG_ACTION.SIGN_UP_SLOT], this.profile.id)
      const filteredEntries = storeLog.filter(entry =>
        new Date(entry.date_reference).getTime() === this.date.getTime(),
      )

      let lastEntryWithOldestDate = null
      let oldestTimestamp = null

      filteredEntries.forEach(entry => {
        const performedAtTimestamp = new Date(entry.performed_at).getTime()

        if (!oldestTimestamp || performedAtTimestamp >= oldestTimestamp) {
          oldestTimestamp = performedAtTimestamp
          lastEntryWithOldestDate = entry
        }
      })

      if (lastEntryWithOldestDate) {
        return this.$dateFormatter.dateTime(new Date(lastEntryWithOldestDate.performed_at), { short: true })
      } else {
        return ''
      }
    },
    storeMember () {
      return StoreData.getters.getStoreMember()
    },
    userId () {
      return DataUser.getters.getUserId()
    },
    isManager () {
      return StoreData.getters.isManager(this.userId)
    },
    getLastFetchDate () {
      const lastFetchDate = this.getLastFetchDateFromUser(this.profile.id)
      const dateFormatterLastFetchDate = this.$dateFormatter.date(lastFetchDate, { short: true })
      return lastFetchDate ? dateFormatterLastFetchDate : this.$i18n('terminology.no_pickups')
    },
    phoneNumber () {
      return PhoneNumbers.callableNumber(this.profile.mobile || this.profile.landline)
    },
    canCopy () {
      return !!navigator.clipboard
    },
    isMe () {
      return DataUser.getters.getUserId() === this.profile.id
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
      const MILLISECONDS_PER_SECOND = 1000
      const userItem = this.storeMember.find(item => item.id === userId)
      const lastFetchTimestamp = userItem?.last_fetch ?? null

      if (lastFetchTimestamp !== null) {
        return new Date(lastFetchTimestamp * MILLISECONDS_PER_SECOND)
      }
    },
    copyIntoClipboard (text) {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          pulseSuccess(this.$i18n('pickup.copiedNumber', { number: text }))
        })
      }
    },
    openChat () {
      this.$refs.takenSlotModal.hide()
      conversationStore.openChatWithUser(this.profile.id)
    },
  },
}
</script>

<style lang="scss" scoped>
.button-container {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}

.slotstatus {
  position: absolute;
  height: 20px;
  width: 20px;
  top: -1em;
  left: 100%;
  border-radius: 50%;
  background-color: var(--fs-color-light);

  &.pending {
    color: var(--fs-color-danger-500);
  }

  &.confirmed {
    color: var(--fs-color-secondary-500);
  }
}

// Check / Clock inside the status patch
.slotstatus-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 1rem;
}

.avatar.pending {
  opacity: 0.45;
}

.profile-name {
  font-size: 1rem;
}
</style>
