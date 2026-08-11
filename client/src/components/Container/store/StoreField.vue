<template>
  <FsLink
    :to="$url('store', entry.id)"
    class="list-group-item list-group-item-action field field--stack"
  >
    <div class="field-container">
      <h6
        v-b-tooltip="entry.name.length > 30 ? entry.name : ''"
        class="field-headline"
        v-text="entry.name"
      />
      <div class="ml-auto d-flex align-items-center">
        <i
          v-b-tooltip="storeCategoryTypeStatus"
          class="fas text-muted"
          :class="storeCategoryTypeIcon"
          style="cursor: help;"
        />
      </div>
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
  </FsLink>
</template>

<script>
import storeEntryMixin from '@/mixins/storeEntryMixin'
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  mixins: [storeEntryMixin],
  props: {
    entry: { type: Object, default: () => {} },
  },
}
</script>
