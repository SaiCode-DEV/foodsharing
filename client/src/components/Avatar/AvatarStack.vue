<template>
  <!-- Flex container reverses elements for correct draw order without z-index -->
  <div class="avatar-stack">
    <div
      v-if="overflowingSlotsCount"
      ref="hidden"
      v-b-tooltip="''"
      class="hidden-users"
    >
      <span v-text="`+${overflowingSlotsCount}`" />
      <b-tooltip
        v-if="showOverflowTooltip && $refs.hidden"
        :target="$refs.hidden"
        triggers="hover"
      >
        <span v-for="(user, index) in hiddenUsers" :key="user.id">
          <span v-if="index != 0">, </span>
          <a :href="$url('profile', user.id)" class="tooltip-link">{{ user.name }}</a>
        </span>
        <br v-if="hiddenUsers.length && freeSlotsCount > shownSlotCounts.free">
        <span v-if="freeSlotsCount > shownSlotCounts.free" v-text="$i18n('pickup.overview.freeSlots', { slots: freeSlotsCount - shownSlotCounts.free })" />
      </b-tooltip>
    </div>

    <i
      v-for="(_, i) in new Array(shownSlotCounts.free)"
      :key="-i"
      class="free-slot fas fa-question"
    />
    <Avatar
      v-for="user in shownUsers"
      :key="user.id"
      :user="user"
      :size="size"
      shape="round"
    />
  </div>
</template>
<script setup>
import { computed, defineProps } from 'vue'
import Avatar from '@/components/Avatar/Avatar.vue'

const props = defineProps({
  users: { type: Array, default: () => [] },
  totalSlots: { type: Number, default: 0 },
  maxWidthInPx: { type: Number, default: 200 },
  size: { type: Number, default: 35 },
  overlap: { type: Number, default: 0.4 },
  showOverflowTooltip: { type: Boolean, default: true },
  joinedTooltip: { type: Boolean, default: false },
  variant: { type: String, default: 'default' },
})

const overlapInPx = computed(() =>
  Math.round(props.size * props.overlap),
)

const availableCirclesCount = computed(() =>
  Math.max(1, Math.floor((props.maxWidthInPx - overlapInPx.value) / (props.size - overlapInPx.value))),
)

const totalSlotsCount = computed(() =>
  Math.max(props.totalSlots, props.users.length),
)

const overflowingSlotsCount = computed(() =>
  totalSlotsCount.value > availableCirclesCount.value
    ? totalSlotsCount.value - availableCirclesCount.value + 1
    : 0,
)

const freeSlotsCount = computed(() =>
  Math.max(0, props.totalSlots - props.users.length),
)

const shownSlotCounts = computed(() => {
  let freeCircles = availableCirclesCount.value
  if (overflowingSlotsCount.value) --freeCircles

  let shownFreeSlotsCount = 0
  if (freeSlotsCount.value > 0) {
    shownFreeSlotsCount = 1
    freeCircles--
  }

  const shownUserSlotsCount = Math.max(
    props.users.length ? 1 : 0,
    Math.min(freeCircles, props.users.length),
  )
  freeCircles -= shownUserSlotsCount

  shownFreeSlotsCount = Math.max(
    shownFreeSlotsCount,
    Math.min(freeSlotsCount.value, freeCircles),
  )

  return {
    users: shownUserSlotsCount,
    free: shownFreeSlotsCount,
  }
})

const shownUsers = computed(() =>
  props.users.slice(0, shownSlotCounts.value.users).reverse(),
)

const hiddenUsers = computed(() =>
  props.users.slice(shownSlotCounts.value.users),
)

const contentColor = computed(() =>
  props.variant === 'default'
    ? 'var(--fs-color-primary-700)'
    : `var(--fs-color-${props.variant}-500)`,
)

const slotBackgroundColor = computed(() =>
  props.variant === 'default'
    ? 'var(--fs-color-light)'
    : `var(--fs-color-${props.variant}-300)`,
)

const backgroundColor = computed(() =>
  props.variant === 'default'
    ? 'white'
    : `var(--fs-color-${props.variant}-500)`,
)
</script>
<style lang="scss" scoped>
::v-deep .b-avatar-img img, .free-slot {
  border: 1px solid v-bind("backgroundColor");
  border-width: 1px 2px 1px 0;
}

.avatar-stack {
  --overlap: v-bind("overlapInPx + 'px'");
  --content-color: v-bind("contentColor");
  --slot-background-color: v-bind("slotBackgroundColor");
  --component-height: v-bind("size + 'px'");
  display: inline-flex;
  flex-direction: row-reverse;

  & *:not(:last-child) {
    margin-left: calc(-1 * var(--overlap));
  }
}

.hidden-users {
  display: flex;
  font-weight: 500;
  padding: 0 6px 0 calc(var(--overlap) + 1px);
  min-width: var(--component-height);
  white-space: nowrap;
  align-items: center;
  border: 1px solid var(--content-color);
  span{
    width: 100%;
    text-align: center;
    margin: 0 -2px;
  }
}

.free-slot {
  width: var(--component-height);
  font-size: calc(var(--component-height) * 0.5);
  align-content: center;
  &:not(:last-child) {
    padding-left: 4px;
  }
}

.hidden-users, .free-slot {
  height: var(--component-height);
  background-color: var(--slot-background-color);
  color: var(--content-color);
  border-radius: var(--component-height);
}

.tooltip-link {
  color: white !important;
}
</style>
