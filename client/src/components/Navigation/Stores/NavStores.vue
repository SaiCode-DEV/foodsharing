<template>
  <Dropdown
    :title="$i18n('navigation.stores')"
    icon="fa-shopping-cart"
    is-fixed-size
    is-scrollable
  >
    <template v-if="hasStores" #content>
      <div
        v-for="(store, key) in getStores"
        :key="key"
        class="store d-flex flex-column align-items-baseline"
      >
        <button
          v-if="getStores.length !== 1"
          v-b-toggle="toggleId(key)"
          role="menuitem"
          target="_self"
          class="dropdown-item dropdown-header text-truncate"
          @click.stop.prevent
        >
          <i
            v-if="store.icon"
            v-b-tooltip="store.tooltip ? $i18n(store.tooltip) : null"
            class="icon-subnav icon--small-container fas"
            :class="[store.icon, {'icon--help': store.tooltip}]"
          />
          <span
            class="text-truncate"
            v-text="$i18n(store.name)"
          />
        </button>
        <b-collapse
          :id="toggleId(key)"
          :accordion="$options.name"
          :visible="key === 0"
          @click.stop.prevent
        >
          <StoresEntry
            v-for="(entry, k) in store.list"
            :key="k"
            :entry="entry"
          />
        </b-collapse>
      </div>
    </template>
    <template v-else #content>
      <small
        role="menuitem"
        class="disabled dropdown-item"
        v-text="$i18n('store.noStores')"
      />
    </template>
    <template #actions>
      <a
        v-if="permissions.addStore"
        :href="$url('storeAdd', homeRegionId)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-plus" />
        {{ $i18n('storeedit.add-new') }}
      </a>
      <a
        :href="$url('storeUserList', userId)"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-list" />
        {{ $i18n('store.all_of_my_stores') }}
      </a>
    </template>
  </Dropdown>
</template>
<script>
// Store
import DataUser from '@/stores/user'
import DataStores from '@/stores/stores'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import StoresEntry from './NavStoresEntry'

export default {
  name: 'MenuStores',
  components: { Dropdown, StoresEntry },
  computed: {
    homeRegionId () {
      return DataUser.getters.getHomeRegion()
    },
    permissions () {
      return DataUser.getters.getPermissions()
    },
    hasStores () {
      return DataStores.getters.hasStores()
    },
    userId () {
      return DataUser.getters.getUserId()
    },
    getStores () {
      return [
        {
          icon: 'fa-users-cog',
          tooltip: 'store.tooltip_managing',
          name: 'dashboard.my.managing_stores',
          list: DataStores.getters.getManaging(),
        },
        {
          icon: 'fa-people-carry',
          tooltip: 'store.tooltip_jumping',
          name: 'dashboard.my.jumping_stores',
          list: DataStores.getters.getJumping(),
        },
        {
          icon: 'fa-shopping-cart',
          name: 'dashboard.my.stores',
          list: DataStores.getters.getOthers(),
        },
      ].filter(e => e.list.length > 0)
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
