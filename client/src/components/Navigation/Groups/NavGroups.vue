<template>
  <Dropdown
    id="dropdown-groups"
    :title="$t('menu.entry.groups')"
    icon="fa-users"
    is-fixed-size
    is-scrollable
  >
    <template v-if="groups.length > 0" #content>
      <GroupsEntry
        v-for="(group, idx) in groups"
        :key="idx"
        :entry="group"
        :is-alone="groups.length === 1"
      />
    </template>
    <template v-else #content>
      <small
        role="menuitem"
        class="disabled dropdown-item"
        v-text="$t('groups.empty')"
      />
    </template>
    <template #actions>
      <a
        :href="$url('workingGroups')"
        role="menuitem"
        class="dropdown-item dropdown-action"
      >
        <i class="icon-subnav fas fa-users" />
        {{ $t('menu.entry.group_overview') }}
      </a>
    </template>
  </Dropdown>
</template>
<script>
// Store
import DataGroups from '@/stores/groups'
// Components
import Dropdown from '../_NavItems/NavDropdown'
import GroupsEntry from './NavGroupsEntry'

export default {
  name: 'MenuGroups',
  components: { Dropdown, GroupsEntry },
  computed: {
    groups () {
      return DataGroups.getters.get()
    },
  },
}
</script>
