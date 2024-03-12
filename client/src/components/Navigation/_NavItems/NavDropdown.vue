<template>
  <b-nav-item-dropdown
    ref="dropdown"
    class="nav-item"
    :right="direction === 'right'"
    :class="{
      'dropdown-menu-fixed-size': isFixedSize,
    }"
  >
    <template #button-content>
      <slot name="badge">
        <span
          v-if="badge"
          class="badge badge-danger"
          :class="{
            'onlyNine': String(badge).length === 1,
            'overNinetyNine': String(badge).length > 2,
          }"
          v-html="badge"
        />
      </slot>
      <slot name="icon">
        <i
          v-if="icon"
          class="icon-nav fas"
          :class="icon"
        />
      </slot>
      <slot name="text">
        <span class="nav-text" v-html="title" />
        <span class="sr-only" v-html="title" />
      </slot>
    </template>
    <b-dropdown-header
      v-if="viewIsMobile"
      class="for-mobile"
    >
      <span>{{ title }}</span>
      <button class="btn btn-link" @click="hide()">
        <i class="fas fa-times" />
      </button>
    </b-dropdown-header>
    <span
      class="content"
      :class="{
        'dropdown-menu-scrollable': isScrollable
      }"
    >
      <slot name="content" />
    </span>
    <b-dropdown-divider v-if="hasActionsSlot" />
    <slot name="actions" />
  </b-nav-item-dropdown>
</template>

<script>
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
export default {
  mixins: [MediaQueryMixin],
  props: {
    title: {
      type: String,
      default: 'Dropdown',
    },
    icon: {
      type: String,
      default: undefined,
    },
    direction: {
      type: String,
      default: 'left',
    },
    badge: {
      type: [String, Number],
      default: 0,
    },
    isScrollable: {
      type: Boolean,
      default: false,
    },
    isFixedSize: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    hasActionsSlot () {
      return this.$slots.actions
    },
  },
  methods: {
    hide () {
      this.$refs.dropdown.hide()
    },
  },
}
</script>
