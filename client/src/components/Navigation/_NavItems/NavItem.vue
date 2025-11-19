<template>
  <Link
    v-if="!isDropdown"
    :title="$t(entry.title)"
    :icon="entry.icon"
    :href="$url(entry.url)"
    :class="{
      'text-success font-weight-bold': entry.isHighlighted,
    }"
  />
  <Dropdown
    v-else
    :title="$t(entry.title)"
    :icon="entry.icon"
    :badge="entry.badge"
    :direction="entry.direction"
    :is-fixed-size="entry.isFixedSize"
    :is-scrollable="entry.isScrollable"
    :class="{
      'is-open-on-mobile': entry.isOpen,
    }"
  >
    <template #content>
      <span
        v-for="(item, idx) in items"
        :key="idx"
      >
        <b-dropdown-divider
          v-if="item.isDivider"
        />
        <b-dropdown-item
          v-else
          :href="item.url ? $url(item.url) : null"
          :target="item.isInternal ? '_self' : '_blank'"
          @click="item.modal ? $bvModal.show(item.modal) : null"
        >
          <i
            v-if="item.icon"
            class="icon-subnav fas"
            :class="item.icon"
          />
          {{ $t(item.title) }}
        </b-dropdown-item>
      </span>
    </template>
  </Dropdown>
</template>

<script>
// Components
import Dropdown from './NavDropdown.vue'
import Link from './NavLink.vue'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  components: {
    Dropdown,
    Link,
  },
  mixins: [MediaQueryMixin, RouteAndDeviceCheckMixin],
  props: {
    entry: {
      type: Object,
      default: () => ({
        title: '',
        url: undefined,
        icon: '',
        direction: '',
        badge: '',
        isScrollable: false,
        isFixedSize: false,
        isInternal: false,
        isDevOnly: false,
        isOpen: false,
        isHighlighted: false,
        items: [],
      }),
    },
  },

  computed: {
    isDropdown () {
      return !this.entry.url && this.entry.items
    },
    items () {
      return this.entry.items.filter(item => {
        if (item.isDevOnly) {
          return this.isDev
        }
        return true
      })
    },
  },
}
</script>
