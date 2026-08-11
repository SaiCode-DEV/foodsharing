<template>
  <Dropdown
    :title="$t('navigation.help_needed')"
    icon="fas fa-question-circle"
    is-fixed-size
    is-scrollable
  >
    <template #content>
      <FsLink
        v-for="(item, idx) in filteredHelpItems"
        :key="idx"
        :to="item.url ? $url(item.url) : '/'"
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="(e) => { if (item.modal) $emit('show-modal', item.modal); else if (!item.url) e.preventDefault(); }"
      >
        <i v-if="item.icon" :class="['icon-subnav', item.icon]" />
        {{ $t(item.title) }}
      </FsLink>
    </template>
  </Dropdown>
</template>

<script setup>
import { computed } from 'vue'
import Dropdown from '../_NavItems/NavDropdown'
import FsLink from '@/components/UI/FsLink.vue'
import MetaNavData from '@/views/partials/Navigation/Data/MetaNavData.json'
import { useEnvironmentCheck } from '@/composables/useEnvironmentCheck'

const { isDev } = useEnvironmentCheck()
const helpItems = MetaNavData.find(item => item.title === 'navigation.help_needed')?.items || []
const filteredHelpItems = computed(() => helpItems.filter(item => !item.isDivider && shouldShowItem(item)))

function shouldShowItem (item) {
  if (item.isDevOnly) {
    return isDev
  }
  return true
}
</script>
