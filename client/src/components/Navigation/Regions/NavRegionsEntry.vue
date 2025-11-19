<template>
  <div class="group text-truncate">
    <button
      v-if="!isAlone"
      v-b-toggle="toggleId(entry.id)"
      role="menuitem"
      class="dropdown-header dropdown-item text-truncate"
      target="_self"
      @click.stop
    >
      <i
        v-if="isHomeRegion"
        v-b-tooltip="$t('dashboard.homeRegion', {region: entry.name})"
        class="icon-subnav fas fa-home"
      />
      <span v-text="entry.name" />
    </button>
    <h6
      v-if="isAlone"
      role="menuitem"
      class="dropdown-header text-truncate"
      v-text="entry.name"
    />
    <b-collapse
      :id="toggleId(entry.id)"
      class="dropdown-submenu"
      accordion="region"
      :visible="isHomeRegion"
    >
      <NavRegionsLinkEntry :entry="entry" :is-linking-subpages="true" />
    </b-collapse>
  </div>
</template>
<script>
// Store
import { useUserStore } from '@/stores/user'
import NavRegionsLinkEntry from '@/components/Navigation/Regions/NavRegionsLinkEntry.vue'

export default {
  name: 'MenuGroupsEntry',
  components: { NavRegionsLinkEntry },
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
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    isHomeRegion () {
      return this.entry.id === this.userStore.getHomeRegion
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
