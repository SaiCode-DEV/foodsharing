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
            v-html="$dateFormatter.dateTime(date)"
          />

          <b-dropdown
            v-if="(isCoordinator || mayEditStore) && !isInPast"
            no-caret
            right
            variant="badge-light"
            class="pickup-options m-2"
          >
            <template #button-content>
              <i class="fas fa-ellipsis-v" />
            </template>
            <b-dropdown-item
              @click="$refs.modal_edit_description.show()"
            >
              <i class="fas fa-pen" />
              {{ $i18n('pickup.edit_description') }}
            </b-dropdown-item>
            <b-dropdown-item
              @click="occupiedSlots.length > 0 ? $refs.modal_delete_error.show() : $refs.modal_delete.show()"
            >
              <i class="fas fa-trash" />
              {{ $i18n('pickup.delete_title') }}
            </b-dropdown-item>
          </b-dropdown>
        </div>
        <div v-if="description">
          <i class="fas fa-info-circle" />
          <i>
            {{ description }}
          </i>
        </div>
      </div>
      <p class="pickup-text">
        <ul class="slots">
          <TakenSlot
            v-for="slot in occupiedSlots"
            :key="`${slot.date}-${slot.profile.id}`"
            :profile="slot.profile"
            :confirmed="slot.isConfirmed"
            :allow-leave="slot.profile.id == user.id && !isInPast"
            :allow-kick="(isCoordinator || mayEditStore) && !isInPast"
            :allow-confirm="(isCoordinator || mayEditStore)"
            :allow-chat="slot.profile.id !== user.id"
            :date="date"
            @leave="$refs.modal_leave.show()"
            @kick="activeSlot = slot, $refs.modal_kick.show()"
            @confirm="$emit('confirm', {date: date, fsId: slot.profile.id})"
          />
          <EmptySlot
            v-for="n in emptySlots"
            :key="n"
            :allow-join="!isUserParticipant && isAvailable && n == 1"
            :allow-remove="(isCoordinator || mayEditStore) && n == emptySlots && !isInPast"
            @join="$refs.modal_join.show(); fetchSameDayPickups(); checkPickupRule()"
            @remove="$emit('remove-slot', date)"
          />
          <div class="add-pickup-slot">
            <button
              v-if="(isCoordinator || mayEditStore) && totalSlots < 10 && !isInPast"
              v-b-tooltip.hover="$i18n('pickup.slot_add')"
              class="btn secondary"
              @click="$emit('add-slot', date)"
            >
              <i class="fas fa-plus" />
            </button>
          </div>
        </ul>
      </p>
    </div>

    <b-modal
      ref="modal_join"
      v-model="showJoinModal"
      :title="$i18n('pickup.join_title_date', $dateFormatter.dateTime(date))"
      :cancel-title="$i18n('pickup.join_cancel')"
      :ok-title="$i18n('pickup.join_agree')"
      :ok-disabled="!loadedUserPickups || !pickupRulePass"
      :ok-variant="okVariant"
      :hide-header-close="true"
      modal-class="bootstrap"
      header-class="d-flex"
      lazy
      @ok="$emit('join', date)"
    >
      <p>{{ $i18n('pickup.really_join_date', slotInfo) }}</p>

      <div v-if="loadedUserPickups && sameDayPickups && sameDayPickups.length">
        <b-alert variant="warning" show>
          {{ $i18n('pickup.same_day_hint', { day: $dateFormatter.date(date) } ) }}
        </b-alert>
        <b-list-group>
          <b-list-group-item
            v-for="pickup in sameDayPickups"
            :key="`${pickup.storeId}-${pickup.date}`"
            :href="$url('store', pickup.storeId)"
            target="_blank"
            class="font-weight-bolder"
          >
            <i class="fas fa-fw" :class="[pickup.isConfirmed ? 'fa-check-circle text-secondary' : 'fa-clock text-danger']" />
            {{
              $i18n('pickup.same_day_entry', {
                when: $dateFormatter.time(pickup.date),
                name: pickup.storeName,
              })
            }}
          </b-list-group-item>
        </b-list-group>
      </div>
      <div v-else-if="!loadedUserPickups">
        <b-alert variant="light" show>
          <i class="fas fa-fw fa-sync fa-spin" />
        </b-alert>
      </div>
      <div v-if="!pickupRulePass">
        <b-alert variant="warning" show>
          {{ $i18n('pickup.region_pickup_rule_failed') }}
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
      :title="$i18n('pickup.really_leave_date_title', { date: $dateFormatter.dateTime(date) })"
      :cancel-title="$i18n('pickup.leave_pickup_message_team')"
      :ok-title="$i18n('pickup.leave_pickup_ok')"
      :hide-header-close="true"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('leave', date)"
      @cancel="$refs.modal_team_message.show()"
    >
      <p>{{ $i18n('pickup.really_leave_date', { date: $dateFormatter.dateTime(date) }) }}</p>
    </b-modal>

    <b-modal
      ref="modal_kick"
      :title="$i18n('pickup.signout_confirm')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.yes_i_am_sure')"
      :hide-header-close="true"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('kick', { 'date': date, 'fsId': activeSlot.profile.id, 'message': kickMessage })"
    >
      <p>
        {{ $i18n('pickup.really_kick_user_info', slotInfo ) }}
      </p>
      <blockquote>
        <div>{{ $i18n('salutation.3') }} {{ slotInfo['name'] }},</div>
        <div>{{ $i18n('pickup.kick_message', slotInfo) }}</div>
        <b-form-textarea
          v-model="kickMessage"
          :placeholder="$i18n('pickup.kick_message_placeholder')"
          max-rows="4"
          maxlength="3000"
        />
        <div>{{ $i18n('pickup.kick_message_footer') }}</div>
      </blockquote>
    </b-modal>

    <b-modal
      ref="modal_team_message"
      :title="$i18n('pickup.leave_team_message_title')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('pickup.team_message_send_and_leave')"
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
      :title="$i18n('pickup.edit_description')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.save')"
      modal-class="bootstrap"
      header-class="d-flex"
      @ok="$emit('edit-description', date, totalSlots, newDescription)"
      @shown="$refs.modal_edit_description_input.focus()"
    >
      <p>
        {{ $i18n('pickup.description_modal_text') }}
      </p>
      <b-form-input
        ref="modal_edit_description_input"
        v-model="newDescription"
        :placeholder="$i18n('pickup.description')"
        :maxlength="100"
      />
      <small v-if="newDescription?.length === 100">
        <i class="fas fa-info-circle" />
        {{ $i18n('pickup.description_max_length_info') }}
      </small>
    </b-modal>

    <b-modal
      ref="modal_delete_error"
      :title="$i18n('pickup.delete_title')"
      ok-only
      modal-class="bootstrap"
    >
      <p>{{ $i18n('pickup.delete_not_empty', slotDate) }}</p>
    </b-modal>

    <b-modal
      ref="modal_delete"
      :title="$i18n('pickup.delete_title')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('delete')"
      modal-class="bootstrap"
      @ok="$emit('delete', date)"
    >
      <p>{{ $i18n('pickup.really_delete_date', slotDate) }}</p>
    </b-modal>
  </div>
</template>

<script>

import { BFormTextarea, BModal, VBTooltip } from 'bootstrap-vue'

import { listSameDayPickupsForUser, checkPickupRuleStore } from '@/api/pickups'

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
      loadedUserPickups: false,
      sameDayPickups: [],
      pickupRulePass: true,
      loadedPickupRule: false,
      okVariant: 'success',
      // cannot use slotDate here since it's computed and needs to avoid circular data references:
      teamMessage: this.$i18n('pickup.leave_team_message_template', { date: this.$dateFormatter.dateTime(this.date) }),
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
  },
  methods: {
    async fetchSameDayPickups () {
      this.sameDayPickups = await listSameDayPickupsForUser(this.user.id, this.date)
      this.loadedUserPickups = true
    },
    async checkPickupRule () {
      this.pickupRulePass = await checkPickupRuleStore(this.user.id, this.storeId, this.date)
      this.okVariant = (!this.pickupRulePass) ? 'danger' : 'success'
      this.loadedPickupRule = true
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
