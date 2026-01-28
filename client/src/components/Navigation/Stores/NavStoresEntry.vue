<template>
  <a
    :href="$url('store', entry.id)"
    role="menuitem"
    class="dropdown-item"
  >
    <i
      v-b-tooltip="pickupStringStatus"
      :class="['icon-subnav', 'fas', storeCategoryTypeIcon, {
        'text-muted-lighter': !entry.pickupStatus,
        'text-primary': entry.pickupStatus === 1,
        'text-warning': entry.pickupStatus === 2,
        'text-danger': entry.pickupStatus === 3,
      }]"
      style="cursor: help;"
    />
    {{ entry.name }}
  </a>
</template>

<script>
import storeEntryMixin from '@/mixins/storeEntryMixin'

export default {
  mixins: [storeEntryMixin],
  props: {
    entry: {
      type: Object,
      default: () => ({}),
    },
  },
}
</script>
<style lang="scss" scoped>
@import '../../../scss/icon-sizes.scss';

.text-transparent {
  color: var(--fs-color-transparent);
}

.text-muted-lighter {
  color: var(--fs-color-gray-300, var(--fs-color-text));
}
</style>
