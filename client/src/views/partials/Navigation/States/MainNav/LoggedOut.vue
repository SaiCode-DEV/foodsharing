<template>
  <ul v-if="mainNav?.length > 0" class="mainnav">
    <Logo v-if="mobile" />
    <span v-for="(category, idx) in mainNav" :key="idx">
      <!-- If the category has a 'url', it is rendered as a link without dropdown -->
      <b-nav-item
        v-if="'url' in category"
        :to="$url(category.url)"
        class="nav-item"
      >
        <i
          v-if="category.icon"
          class="icon-nav fas"
          :class="category.icon"
        />
        <span class="nav-text" v-text="$t(category.title)" />
      </b-nav-item>
      <Dropdown
        v-else
        :key="'dropdown-'+idx"
        :title="$t(category.title)"
        :icon="category.icon"
        is-fixed-size
      >
        <template #icon>
          <img
            v-if="category.title === 'menu.entry.donation_menu'"
            src="/img/icon/donation-strawberry.svg"
            class="pr-1"
            width="40"
            height="25"
          >
        </template>
        <template #content>
          <span
            v-for="(entry, key) in category.items"
            :key="key"
          >
            <FsLink
              :to="$url(entry.url)"
              role="menuitem"
              class="dropdown-item dropdown-action"
            >
              {{ $t(entry.title) }}
            </FsLink>
          </span>
        </template>
      </Dropdown>
    </span>

    <b-navbar-toggle
      target="nav-collapse"
      :title="$t('navigation.toggle')"
    >
      <template #default="{ expanded }">
        <i
          class="fas"
          :class="{
            'fa-bars': !expanded,
            'fa-times': expanded,
          }"
        />
      </template>
    </b-navbar-toggle>
  </ul>
</template>

<script setup>
import MainNavData from '../../Data/MainNavData.json'
import Dropdown from '@/components/Navigation/_NavItems/NavDropdown'
import Logo from '@/components/Navigation/Logo'
import FsLink from '@/components/UI/FsLink.vue'
import { useMediaQuery } from '@/composables/useMediaQuery'
import { useNavFilter } from '@/composables/useNavFilter'
import { computed } from 'vue'

const { mobile } = useMediaQuery()
const { withVisibleItems } = useNavFilter()

const mainNav = computed(() => {
  if (mobile && mobile.value) {
    const fundraising = MainNavData.fundraising
    return fundraising ? [withVisibleItems(fundraising)] : []
  }
  return Object.keys(MainNavData).map(key => withVisibleItems(MainNavData[key]))
})
</script>
