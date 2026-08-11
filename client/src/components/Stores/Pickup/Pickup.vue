<template>
  <div>
    <div class="pickup">
      <div class="pickup-title">
        <div v-if="storeTitle" class="store-title">
          <strong>{{ storeTitle }}</strong>
        </div>
        <div
          class="pickup-date"
          :class="{'today': isToday, 'past': isInPast, 'soon': isSoon, 'empty': emptySlots > 0, 'coord': (isCoordinator || mayEditStore)}"
        >
          <span
            v-text="$dateFormatter.dateTime(date)"
          />

          <b-dropdown
            v-if="canEditSlot || canMultiChat"
            no-caret
            right
            variant="badge-light"
            class="pickup-options m-2"
            data-test="pickup-options-dropdown"
          >
            <template #button-content>
              <i class="fas fa-ellipsis-v" />
            </template>
            <b-dropdown-item
              v-if="canMultiChat"
              data-test="slot-multi-chat"
              @click="openMultiChat()"
            >
              <i class="fas fa-comments" />
              {{ $t('pickup.chat') }}
            </b-dropdown-item>
            <b-dropdown-item
              v-if="canEditSlot"
              @click="$refs.modal_edit_description.show()"
            >
              <i class="fas fa-pen" />
              {{ $t('pickup.edit_description') }}
            </b-dropdown-item>
            <b-dropdown-item
              v-if="canEditSlot"
              @click="occupiedSlots.length > 0 ? $refs.modal_delete_error.show() : $refs.modal_delete.show()"
            >
              <i class="fas fa-trash" />
              {{ $t('pickup.delete_title') }}
            </b-dropdown-item>
          </b-dropdown>
        </div>
        <div v-if="description">
          <i class="fas fa-info-circle" />
          <i>
            {{ description }}
          </i>
        </div>
        <div
          v-if="!passportStillValid && !isInPast"
          v-b-tooltip="$t('pickup.passport_expired.long')"
          class="text-danger"
        >
          <i class="fas fa-id-card" />
          {{ $t('pickup.passport_expired.short') }}
        </div>
      </div>
      <div class="pickup-text">
        <ul class="slots">
          <TakenSlot
            v-for="slot in occupiedSlots"
            :key="`${slot.signUpDate}-${slot.profile.id}`"
            :profile="slot.profile"
            :confirmed="slot.isConfirmed"
            :sign-up-date="new Date(slot.signUpDate)"
            :allow-leave="slot.profile.id == user.id && !isInPast"
            :allow-kick="(isCoordinator || mayEditStore) && !isInPast"
            :allow-confirm="(isCoordinator || mayEditStore)"
            :allow-chat="slot.profile.id !== user.id"
            :date="date"
            :store-name="storeTitle"
            @leave="$refs.modal_leave.show()"
            @kick="activeSlot = slot, $refs.modal_kick.show()"
            @confirm="$emit('confirm', {date: date, fsId: slot.profile.id})"
          />
          <EmptySlot
            v-for="n in emptySlots"
            :key="n"
            :allow-join="!isUserParticipant && isAvailable && n == 1 && passportStillValid"
            :passport-still-valid="passportStillValid"
            :allow-remove="(isCoordinator || mayEditStore) && n == emptySlots && !isInPast"
            @join="$refs.modal_join.show(); fetchSameDayAgenda(); checkPickupRule()"
            @remove-direct="$emit('remove-slot', true)"
            @remove-menu="$emit('remove-slot', false)"
          />
          <div class="add-pickup-slot">
            <button
              v-if="(isCoordinator || mayEditStore) && totalSlots < maxCountPickupSlot && !isInPast"
              v-b-tooltip.hover="$t('pickup.slot_add')"
              class="btn secondary"
              @click="$emit('add-slot', date)"
            >
              <i class="fas fa-plus" />
            </button>
          </div>
        </ul>
      </div>
    </div>

    <b-modal
      ref="modal_join"
      v-model="showJoinModal"
      :title="$t('pickup.join_title')"
      :cancel-title="$t('pickup.join_cancel')"
      :ok-title="$t('pickup.join_agree')"
      :ok-disabled="!loadedUserAgenda || !pickupRulePass || isMissingHygieneCertificate || !isTeamMember"
      :ok-variant="okVariant"
      :hide-header-close="true"
      modal-class="bootstrap"
      header-class="d-flex"
      lazy
      @ok="$emit('join', date)"
    >
      <b-alert :show="!isTeamMember" variant="danger">
        <i class="fas fa-user-slash" />
        <span v-text="$t('pickup.membershipMissing')" />
      </b-alert>
      <b-alert :show="isMissingHygieneCertificate && isTeamMember" variant="danger">
        <i class="fas fa-hands-wash" />
        <span v-if="hygieneCertificateUntil" v-text="$t('pickup.hygieneCertificateMissing.timeout')" />
        <span v-else v-text="$t('pickup.hygieneCertificateMissing.none')" />
        <a :href="$url('settingsHygiene')" v-text="$t('pickup.hygieneCertificateMissing.link')" />
      </b-alert>

      <p>{{ $t('pickup.really_join_date', slotInfo) }}</p>

      <div v-if="loadedUserAgenda && sameDayAgenda && sameDayAgenda.length > 1">
        <b-alert variant="warning" show>
          {{ $t('pickup.same_day_hint' ) }}
        </b-alert>
        <b-list-group>
          <b-list-group-item
            v-for="item in sameDayAgenda"
            :key="`${item.type}-${item.id}-${item.date}`"
            :to="item.id > 0 ? $url(item.type, item.id) : undefined"
            target="_blank"
            class="font-weight-bolder"
            :class="{ 'list-group-item-warning': item.type === 'proposal' }"
          >
            <i class="fas fa-fw" :class="agendaStatusIcon(item)" />
            <i class="fas fa-fw" :class="agendaTypeIcon(item)" />
            {{
              $t('pickup.same_day_entry', {
                when: format_agenda_date(item),
                name: item.type !== 'proposal' ? item.name : storeTitle,
              })
            }}
          </b-list-group-item>
        </b-list-group>
      </div>
      <div v-else-if="!loadedUserAgenda">
        <b-alert variant="light" show>
          <i class="fas fa-fw fa-sync fa-spin" />
        </b-alert>
      </div>
      <div v-if="!pickupRulePass">
        <b-alert variant="warning" show>
          {{ $t('pickup.region_pickup_rule_failed') }}
        </b-alert>
      </div>
      <div v-if="!loadedPickupRule">
        <b-alert variant="light" show>
          <i class="fas fa-fw fa-sync fa-spin" />
        </b-alert>
      </div>
    </b-modal>

    <b-modal
      ref="modal_leave"
      :title="$t('pickup.really_leave_date_title', { date: $dateFormatter.dateTime(date) })"
      :cancel-title="$t('pickup.leave_pickup_message_team')"
      :ok-title="$t('pickup.leave_pickup_ok')"
      :hide-header-close="true"
      modal-class="bootstrap"
      ok-variant="secondary"
      header-class="d-flex"
      @ok="$emit('leave', date)"
      @cancel="$refs.modal_team_message.show()"
    >
      <p>{{ $t('pickup.really_leave') }}</p>
    </b-modal>

    <b-modal
      ref="modal_kick"
      :title="$t('pickup.signout_confirm')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.yes_i_am_sure')"
      :hide-header-close="true"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('kick', { 'date': date, 'fsId': activeSlot.profile.id, 'message': kickMessage })"
    >
      <p>
        {{ $t('pickup.really_kick_user_info', slotInfo ) }}
      </p>
      <blockquote>
        <div>{{ $t('salutation.3') }} {{ slotInfo['name'] }},</div>
        <div>{{ $t('pickup.kick_message', slotInfo) }}</div>
        <b-form-textarea
          v-model="kickMessage"
          :placeholder="$t('pickup.kick_message_placeholder')"
          max-rows="4"
          maxlength="3000"
        />
        <div>{{ $t('pickup.kick_message_footer') }}</div>
      </blockquote>
    </b-modal>

    <b-modal
      ref="modal_team_message"
      :title="$t('pickup.leave_team_message_title')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('pickup.team_message_send_and_leave')"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('team-message', teamMessage); $emit('leave', date)"
    >
      <b-form-textarea
        v-model="teamMessage"
        rows="4"
      />
    </b-modal>

    <b-modal
      ref="modal_edit_description"
      :title="$t('pickup.edit_description')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('edit-description', date, totalSlots, newDescription)"
      @shown="$refs.modal_edit_description_input.focus()"
    >
      <p>
        {{ $t('pickup.description_modal_text') }}
      </p>
      <b-form-input
        ref="modal_edit_description_input"
        v-model="newDescription"
        :placeholder="$t('pickup.description')"
        :maxlength="100"
      />
      <small v-if="newDescription?.length === 100">
        <i class="fas fa-info-circle" />
        {{ $t('pickup.description_max_length_info') }}
      </small>
    </b-modal>

    <b-modal
      ref="modal_delete_error"
      :title="$t('pickup.delete_title')"
      ok-only
      modal-class="bootstrap"
    >
      <p>{{ $t('pickup.delete_not_empty') }}</p>
    </b-modal>

    <b-modal
      ref="modal_delete"
      :title="$t('pickup.delete_title')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('delete')"
      modal-class="bootstrap"
      @ok="$emit('delete', date)"
    >
      <p>{{ $t('pickup.really_delete_date', slotDate) }}</p>
    </b-modal>
  </div>
</template>

<script>
import conversationStore from '@/stores/conversations'
import { BFormTextarea, BModal, VBTooltip } from 'bootstrap-vue'

import { listSameDayAgendaForUser, checkPickupRuleStore } from '@/api/pickups'
import StoreData from '@/stores/stores'
import { useStoreStore } from '@/stores/store'

import TakenSlot from '@/components/Stores/Pickup/TakenSlot.vue'
import EmptySlot from '@/components/Stores/Pickup/EmptySlot.vue'

export default {
  components: { EmptySlot, TakenSlot, BFormTextarea, BModal },
  directives: { VBTooltip },
  props: {
    storeId: { type: Number, required: true },
    storeTitle: { type: String, default: '' },
    date: { type: Date, required: true },
    showRelativeDate: { type: Boolean, default: false },
    isAvailable: { type: Boolean, default: false },
    totalSlots: { type: Number, default: 0 },
    occupiedSlots: { type: Array, default: () => [] },
    mayEditStore: { type: Boolean, default: false },
    isCoordinator: { type: Boolean, default: false },
    user: { type: Object, default: () => { return { id: null } } },
    description: { type: String, default: () => { return null } },
    passportStillValid: { type: Boolean, default: false },
  },
  setup () {
    return {
      storeStore: useStoreStore(),
    }
  },
  data () {
    return {
      showJoinModal: false,
      activeSlot: {
        profile: {
          name: '',
          id: null,
        },
      },
      loadedUserAgenda: false,
      sameDayAgenda: [],
      pickupRulePass: true,
      loadedPickupRule: false,
      okVariant: 'success',
      // cannot use slotDate here since it's computed and needs to avoid circular data references:
      teamMessage: this.$t('pickup.leave_team_message_template', { date: this.$dateFormatter.dateTime(this.date) }),
      kickMessage: '',
      newDescription: this.description,
    }
  },
  computed: {
    slotDate () {
      return {
        date: this.$dateFormatter.dateTime(this.date),
      }
    },
    slotInfo () {
      return {
        date: this.$dateFormatter.dateTime(this.date),
        storeName: this.storeTitle,
        name: this.activeSlot.profile.name,
      }
    },
    maxCountPickupSlot () {
      return this.storeStore.getMaxCountPickupSlot
    },
    isUserParticipant () {
      return this.occupiedSlots.findIndex((e) => {
        return e.profile.id === this.user.id
      }) !== -1
    },
    isInPast () {
      return this.$dateFormatter.isPast(this.date)
    },
    isInFewHours () {
      return this.$dateFormatter.getDifferenceToNowInHours(this.date) < 4
    },
    isSoon () {
      return this.$dateFormatter.getDifferenceToNowInDays(this.date) <= 3
    },
    isToday () {
      return this.$dateFormatter.isToday(this.date)
    },
    emptySlots () {
      return Math.max(this.totalSlots - this.occupiedSlots.length, 0)
    },
    isHygieneRequired () {
      return StoreData.getters.getStoreInformation().isHygieneRequired
    },
    hygieneCertificateUntil () {
      return StoreData.getters.getStoreMember().find(member => member.id === this.user.id)?.hygieneCertificateUntil ?? null
    },
    isMissingHygieneCertificate () {
      return this.isHygieneRequired && (
        !this.hygieneCertificateUntil ||
        new Date(this.hygieneCertificateUntil) < this.date
      )
    },
    isTeamMember () {
      return !(this.mayEditStore && !StoreData.getters.getStoreMember().find(member => member.id === this.user.id))
    },
    canEditSlot () {
      return (this.isCoordinator || this.mayEditStore) && !this.isInPast
    },
    canMultiChat () {
      // Check if there is at least one *other* user signed up for this pickup
      if (!this.occupiedSlots || !this.user || !this.user.id) return false
      return this.occupiedSlots.some(slot => slot.profile && slot.profile.id !== this.user.id)
    },
  },
  methods: {
    async fetchSameDayAgenda () {
      this.sameDayAgenda = await listSameDayAgendaForUser(this.user.id, this.date)

      // Add proposal entry
      this.sameDayAgenda.push({
        type: 'proposal',
        id: -1,
        name: null,
        date: this.date,
      })
      this.sameDayAgenda.sort((a, b) => a.date - b.date)

      this.loadedUserAgenda = true
    },
    async checkPickupRule () {
      this.pickupRulePass = await checkPickupRuleStore(this.storeId, this.date)
      this.okVariant = (!this.pickupRulePass) ? 'danger' : 'success'
      this.loadedPickupRule = true
    },
    openMultiChat () {
      const storeUrl = this.$url('store', this.storeId)
      const preface = {
        content: this.$t('pickup.chat_preface', { date: this.$dateFormatter.dateTime(this.date, { short: true }), store: this.storeTitle, storeUrl }),
        username: this.storeTitle,
      }
      conversationStore.openMultiChat(this.occupiedSlots.map(slot => slot.profile.id), preface)
    },
    agendaStatusIcon (item) {
      if (item.type === 'store') {
        return item.isConfirmed
          ? 'fa-check-circle text-secondary'
          : 'fa-clock text-danger'
      }

      if (item.type === 'proposal') {
        return 'fa-question-circle text-danger'
      }

      // else: event
      return item.status === 'accepted'
        ? 'fa-check-circle text-secondary'
        : item.status === 'invited'
          ? 'fa-envelope text-primary'
          : item.status === 'maybe'
            ? 'fa-question-circle text-warning'
            : 'fa-question-circle text-danger'
    },
    agendaTypeIcon (item) {
      return item.type === 'event' ? 'fa-calendar-alt' : 'fa-shopping-cart'
    },
    format_agenda_date (item) {
      if (!item?.end) {
        // No end time, return just the time
        return this.$dateFormatter.time(item.date)
      }

      // else: this is an event, we have start and end dates
      const isOnSameDay = this.$dateFormatter.date(item.date) === this.$dateFormatter.date(item.end)
      if (isOnSameDay) {
        // same-day event
        return this.$dateFormatter.time(item.date) + ' - ' + this.$dateFormatter.time(item.end)
      }

      // multi-day event
      return this.$dateFormatter.dateTime(item.date) + ' - ' + this.$dateFormatter.dateTime(item.end)
    },
  },
}
</script>

<style lang="scss" scoped>
.pickup {
  position: relative;
}

.pickup-date {
  padding-bottom: 5px;
  font-size: 0.875rem;

  &.today {
    &:not(.past) {
      font-weight: bolder;
    }
  }

  // Pickup marker to explain traffic lights
  &.coord.soon.empty::after {
    float: right;
    margin-right: 1em;
    text-align: right;
    content: "\f12a"; // fa-exclamation
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    color: var(--fs-color-warning-500);
  }
  &.coord.soon.empty.today::after {
    color: var(--fs-color-danger-500);
  }
  &.coord.past::after {
    content: "" !important;
  }
}

.pickup-block:not(:last-of-type) {
  .pickup-text {
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--fs-border-default);
  }
}

// The container for one pickup
.pickup {
  .store-title {
    display: none;
  }

  .pickup-title,
  .store-title {
    font-size: inherit;
  }

  .pickup-text {
    margin-left: -10px;
    margin-right: -10px;
    padding-left: 10px;
    padding-right: 10px;
  }

  // The list of slots for one pickup
  ul.slots {
    display: flex;
    padding: 0;
    margin: 0 0 5px;
    flex-wrap: wrap;

    div {
      display: inline-block;
    }

    ::v-deep .btn {
      // position: relative;
      display: inline-block;
      margin: 2px;
      margin-left: 1px;
      width: 50px;
      height: 50px;
      color: var(--fs-color-primary-400);
      background-color: var(--fs-color-primary-100);
      border: 2px solid  var(--fs-color-primary-300);

      &:hover {
        border-color: var(--fs-color-primary-500);
      }
      &:focus {
        box-shadow: none;
      }
      &.filled {
        overflow: hidden;
      }
      &.btn-primary {
        background-color: var(--fs-color-primary-300);
      }
      &[disabled] {
        opacity: 1;
      }
      &[disabled]:hover {
        border-color: var(--fs-color-primary-300);
        cursor: default;
      }
    }
  }

  /* Display deletion button only when hovering pickup date */
  .delete-pickup {
    display: none;
    position: absolute;
    top: -4px;
    right: -9px;
    color: var(--fs-color-primary-500);
    background-color: var(--fs-color-light);
    opacity: 0.9;

    .btn {
      padding: 3px 5px;
      line-height: 1.38;
    }
  }

  &:hover .delete-pickup {
    display: block;
  }

  .soon .delete-pickup {
    right: 1px;
  }
}

.modal-dialog {
  blockquote {
    margin: 0;
    padding-left: 0.5rem;
    border-left: 3px solid var(--fs-color-info-200);

    div {
      margin: 0.25rem;
    }

    textarea[wrap="soft"] {
      overflow-y: auto !important;
    }
  }
}

.pickup-options {
  position: absolute;
  top: -1.2em;
  right: -1.5em;

  ::v-deep .btn:focus {
    box-shadow: none !important;
  }
}

</style>
