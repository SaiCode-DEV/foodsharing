<template>
  <a
    class="list-group-item list-group-item-action field field--stack"
    :href="$url('store', entry.id)"
  >
    <div class="field-container">
      <h6
        v-b-tooltip="entry.name.length > 30 ? entry.name : ''"
        class="field-headline"
        v-text="entry.name"
      />
      <i
        v-if="entry.isManaging"
        v-b-tooltip="$t('store.tooltip_managing')"
        class="fas fa-users-cog text-muted"
        style="cursor: help;"
      />
      <i
        v-if="entry.membershipStatus === 2"
        v-b-tooltip="$t('store.tooltip_jumping')"
        class="fas fa-people-carry text-muted"
        style="cursor: help;"
      />
    </div>
    <div
      v-if="entry.pickupStatus > 0"
      class="d-flex align-items-center"
    >
      <i
        class="fas fa-circle mr-1"
        :class="{
          'text-primary': entry.pickupStatus === 1,
          'text-warning': entry.pickupStatus === 2,
          'text-danger': entry.pickupStatus === 3
        }"
      />
      <small
        class="field-subline"
        v-text="$t('store.short_tooltip_'+['yellow', 'orange', 'red'][entry.pickupStatus - 1])"
      />
    </div>
  </a>
</template>

<script>
export default {
  props: {
    entry: { type: Object, default: () => {} },
  },
}
</script>
