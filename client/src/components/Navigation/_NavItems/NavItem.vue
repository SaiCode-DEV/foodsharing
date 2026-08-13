<template>
  <NavLink
    v-if="!isDropdown"
    :title="$t(entry.title)"
    :icon="entry.icon"
    :to="routerTarget(entry.url)"
    :href="externalHref(entry.url)"
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
          :to="routerTarget(item.url)"
          :href="externalHref(item.url)"
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
import NavLink from './NavLink.vue'
// Mixins
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import RouteAndDeviceCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'
// Helpers
import { isExternalUrl } from '@/helper/urls'

export default {
  components: {
    Dropdown,
    NavLink,
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
  methods: {
    /**
     * Entries that only open a modal have no url, so the url key has to be checked before
     * resolving it. Internal targets are handed to the router, external ones stay plain links.
     */
    entryUrl (urlKey) {
      return urlKey ? this.$url(urlKey) : null
    },
    routerTarget (urlKey) {
      const url = this.entryUrl(urlKey)
      return url && !isExternalUrl(url) ? url : null
    },
    externalHref (urlKey) {
      const url = this.entryUrl(urlKey)
      return url && isExternalUrl(url) ? url : null
    },
  },
}
</script>
