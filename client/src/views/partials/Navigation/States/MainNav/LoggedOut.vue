<template>
  <ul class="mainnav">
    <Logo v-if="mobile" />
    <span v-for="(category, idx) in mainNav" :key="idx">
      <!-- If the category has a 'url', it is rendered as a link without dropdown -->
      <b-nav-item
        v-if="'url' in category"
        :href="$url(category.url)"
        class="nav-item"
      >
        <i
          v-if="category.icon"
          class="icon-nav fas"
          :class="category.icon"
        />
        <span class="nav-text" v-text="$i18n(category.title)" />
      </b-nav-item>
      <Dropdown
        v-else
        :title="$i18n(category.title)"
        :icon="category.icon"
      >
        <template #content>
          <a
            v-for="(entry, key) in category.items"
            :key="key"
            :href="$url(entry.url)"
            role="menuitem"
            class="dropdown-item dropdown-action"
            v-text="$i18n(entry.title)"
          />
        </template>
      </Dropdown>
    </span>

    <b-navbar-toggle
      target="nav-collapse"
      :title="$i18n('navigation.toggle')"
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
import { useMediaQuery } from '@/composables/useMediaQuery'

const { mobile } = useMediaQuery()
const mainNav = MainNavData
</script>
