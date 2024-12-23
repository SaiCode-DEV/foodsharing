<template>
  <Dropdown
    :title="$i18n('navigation.regions')"
    icon="fa-globe"
    is-fixed-size
    is-scrollable
  >
    <template v-if="regions.length > 0" #content>
      <RegionsEntry
        v-for="region in regions"
        :key="region.id"
        :entry="region"
      />
    </template>
    <template v-else #content>
      <small
        role="menuitem"
        class="disabled dropdown-item"
        v-text="$i18n('region.none')"
      />
    </template>
    <template #actions>
      <button
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="$bvModal.show('joinRegionModal')"
      >
        <i class="icon-subnav fas fa-plus" />
        {{ $i18n('menu.entry.joinregion') }}
      </button>
    </template>
  </Dropdown>
</template>
<script>
// Store
// Store
import { useUserStore } from '@/stores/user'
import { useRegionStore } from '@/stores/regions'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import RegionsEntry from './NavRegionsEntry'

export default {
  name: 'MenuRegions',
  components: { Dropdown, RegionsEntry },
  setup () {
    const userStore = useUserStore()
    const regionStore = useRegionStore()
    return {
      userStore,
      regionStore,
    }
  },
  computed: {
    regions () {
      const homeRegion = this.userStore.getHomeRegion
      return this.regionStore.regions.slice().sort((a, b) => {
        if (a.id === homeRegion) return -1
        if (b.id === homeRegion) return 1
        else return a.name.localeCompare(b.name)
      })
    },
  },
}
</script>
