<template>
  <Dropdown
    :title="$t('navigation.help_needed')"
    icon="fas fa-question-circle"
    is-fixed-size
    is-scrollable
  >
    <template #content>
      <a
        v-for="(item, idx) in filteredHelpItems"
        :key="idx"
        :href="item.url ? $url(item.url) : undefined"
        role="menuitem"
        class="dropdown-item dropdown-action"
        @click="item.modal && $emit('show-modal', item.modal)"
      >
        <i v-if="item.icon" :class="['icon-subnav', item.icon]" />
        {{ $t(item.title) }}
      </a>
    </template>
  </Dropdown>
</template>

<script setup>
import { computed } from 'vue'
import Dropdown from '../_NavItems/NavDropdown'
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
