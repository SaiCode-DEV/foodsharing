<template>
  <a
    class="pickup-field list-group-item list-group-item-action field field--stack"
    :class="{
      'pickup-field--option': entry.isConfirmed === null,
      'pickup-field--need-confirm': entry.isConfirmed === false,
      'pickup-field--success': entry.isConfirmed === true,
      'muted': muteSignUps && entry.isConfirmed !== null,
    }"
    :href="$url('store', entry.store.id)"
  >
    <div class="d-flex justify-content-between align-items-center">
      <h6
        class="field-headline field-headline--big"
        :class="{
          'text-danger': isSoon
        }"
      >
        {{ $dateFormatter.date(date, {type: 'full'}) }}
      </h6>
      <h6
        v-if="isSoon"
        class="field-headline field-headline--big text-danger"
      >
        <Time
          :time="entry.date"
          plain
          :options="{ short: true }"
        />
      </h6>
      <h6
        v-else
        class="field-headline field-headline--big"
      >
        {{ $dateFormatter.time(date) }}
      </h6>
    </div>
    <p class="field-container m-0">
      <small
        v-b-tooltip="entry.store.name.length > 30 ? entry.store.name : ''"
        class="field-subline"
        v-text="entry.store.name"
      />
      <span
        class="pickup-status badge badge-pill d-flex p-1 align-items-center"
        :class="{
          'badge-info': entry.isConfirmed === null,
          'badge-danger': entry.isConfirmed === false,
          'badge-success': entry.isConfirmed === true,
        }"
      >
        <i
          v-b-tooltip="iconTooltip"
          class="fas"
          :class="{
            'fa-question-circle': entry.isConfirmed === null,
            'fa-clock': entry.isConfirmed === false,
            'fa-check-circle': entry.isConfirmed === true,
          }"
        />
        <span
          v-if="entry.slots > 4"
          v-b-tooltip="entry.occupiedSlots.map(e=> e.name).join(', ')"
          class="slots"
        >
          {{ entry.occupiedSlots.length }} / {{ entry.slots }}
        </span>
        <span
          v-else-if="entry.slots !== 1"
          v-b-tooltip="entry.occupiedSlots.map(e=> e.name).join(', ')"
          class="slots"
        >
          <!-- TODO replace with <AvatarStack /> -->
          <span
            v-for="(slot, key) in team"
            :key="key"
            class="slot"
          >
            <Avatar
              v-if="slot"
              :user="{ avatar: slot.avatar }"
              :size="20"
              shape="round"
              class="slot-user"
            />
            <i
              v-else
              class="slot-free fas fa-question"
            />
          </span>
        </span>
      </span>
    </p>
    <p
      v-if="entry.description"
      class="field-container m-0"
    >
      <small class="pickup-description">
        <i
          v-b-tooltip="entry.description"
          class="fas fa-info-circle"
        />
        <i>
          {{ entry.description }}
        </i>
      </small>
    </p>
  </a>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'

export default {
  components: { Avatar, Time },
  props: {
    entry: { type: Object, default: () => ({}) },
    muteSignUps: { type: Boolean, default: false },
  },
  computed: {
    isSoon () {
      return this.$dateFormatter.getDifferenceToNowInHours(this.date) < 4
    },
    team () {
      const freeSlots = Math.max(0, this.entry.slots - this.entry.occupiedSlots.length)
      return [...this.entry.occupiedSlots, ...new Array(freeSlots).fill(null)].reverse()
    },
    date () {
      return new Date(this.entry.date)
    },
    iconTooltip () {
      if (this.entry.isConfirmed === null) return this.$i18n('pickup.may_enter')
      if (this.entry.isConfirmed === false) return this.$i18n('pickup.to_be_confirmed')
      return ''
    },
  },
}
</script>
<style lang="scss" scoped>
@import '../../../scss/colors.scss';

.pickup-status {
  font-size: 1rem;
}

.pickup-status-time {
  font-size: 0.9rem;
}

.slots {
  margin: 0 0.25rem;
  display: inline-flex;
  flex-direction: row-reverse;
  font-size: 0.875rem;
}

$size: 1.25rem;
.slot {
  width: $size;
  height: 1rem;
  display: flex;
  align-items: center;

  &:not(:last-child) {
    margin-left: -0.55rem;
  }
}

.pickup-field--option {
  --light-slot-color: var(--fs-color-info-300);
  --slot-color: var(--fs-color-info-500);
}
.pickup-field--need-confirm {
  --light-slot-color: var(--fs-color-danger-300);
  --slot-color: var(--fs-color-danger-500);
}
.pickup-field--success {
  --light-slot-color: var(--fs-color-success-300);
  --slot-color: var(--fs-color-success-500);
}

::v-deep.slot-user {
  width: $size;
  height: $size;
  border-radius: 50%;
  overflow: hidden;
  border: 2px var(--slot-color) solid;
}

.slot-free {
  width: $size;
  height: $size;
  font-size: .5rem;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--light-slot-color);
  color: var(--slot-color);
  border: 2px currentColor solid;
}

.pickup-description {
  overflow: hidden;
  text-overflow: ellipsis;
}

.pickup-field.muted {
  padding-top: 0.25em;
  padding-bottom: 0.5em;
  > * { opacity: 0.5 }
}
</style>
