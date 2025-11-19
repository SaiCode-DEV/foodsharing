<template>
  <a
    :href="$url('store', entry.id)"
    role="menuitem"
    class="dropdown-item"
  >
    <i
      v-b-tooltip="pickupStringStatus"
      class="icon-subnav fas fa-circle"
      :class="{
        'text-transparent': !entry.pickupStatus,
        'text-primary': entry.pickupStatus === 1,
        'text-warning': entry.pickupStatus === 2,
        'text-danger': entry.pickupStatus === 3,
        'icon--help': entry.pickupStatus > 0,
      }"
    />
    {{ entry.name }}
  </a>
</template>

<script>

export default {
  props: {
    entry: {
      type: Object,
      default: () => ({}),
    },
  },
  computed: {
    pickupStringStatus () {
      if (this.entry.pickupStatus > 0) {
        return this.$t('store.tooltip_' + ['yellow', 'orange', 'red'][this.entry.pickupStatus - 1])
      }
      return ''
    },
  },
}
</script>
<style lang="scss" scoped>
@import '../../../scss/icon-sizes.scss';

.text-transparent {
  color: var(--fs-color-transparent);
}
</style>
