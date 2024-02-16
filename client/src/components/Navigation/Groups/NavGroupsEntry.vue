<template>
  <div class="group text-truncate">
    <button
      v-if="!isAlone"
      v-b-toggle="toggleId(entry.id)"
      role="menuitem"
      class="dropdown-header dropdown-item text-truncate"
      target="_self"
      @click.stop
      v-html="entry.name"
    />
    <h6
      v-if="isAlone"
      role="menuitem"
      class="dropdown-header text-truncate"
      v-html="entry.name"
    />
    <b-collapse
      :id="toggleId(entry.id)"
      :visible="isAlone"
      class="dropdown-submenu"
      accordion="groups"
    >
      <NavGroupsLinkEntry :entry="entry" />
    </b-collapse>
  </div>
</template>
<script>
import NavGroupsLinkEntry from '@/components/Navigation/Groups/NavGroupsLinkEntry.vue'

export default {
  name: 'MenuGroupsEntry',
  components: { NavGroupsLinkEntry },
  props: {
    isAlone: {
      type: Boolean,
      default: false,
    },
    entry: {
      type: Object,
      default: () => {},
    },
  },
  methods: {
    toggleId (id) {
      return this.$options.name + '_' + id
    },
  },
}
</script>

<style lang="scss" scoped>
@import '../../../scss/icon-sizes.scss';
</style>
