<template>
  <ul class="mainnav">
    <Logo v-if="viewIsMobile" />
    <Dropdown
      v-for="(category, idx) in mainNav"
      :key="idx"
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

    <b-navbar-toggle target="nav-collapse">
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

<script>
//
import MainNavData from '../../Data/MainNavData.json'
//
import Dropdown from '@/components/Navigation/_NavItems/NavDropdown'
import Logo from '@/components/Navigation/Logo'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: {
    Logo,
    Dropdown,
  },
  mixins: [MediaQueryMixin],
  data () {
    return {
      mainNav: MainNavData,
    }
  },
}
</script>
