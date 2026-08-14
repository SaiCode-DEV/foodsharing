<template>
  <b-dropdown
    v-if="showOverflowMenu"
    v-b-tooltip.hover.noninteractive="title"
    no-caret
    right
    variant="none"
    class="overflow-menu"
    :class="[floatRight ? 'float-right' : '', `${variant}-variant`]"
    :dropup="direction === 'up'"
    :dropleft="direction === 'left'"
    :dropright="direction === 'right'"
  >
    <template #button-content>
      <i :class="`fas fa-${icon}`" aria-hidden="true" />
      <span class="sr-only">{{ title }}</span>
    </template>
    <b-dropdown-item
      v-for="(option, i) in activeOptions"
      :key="i"
      :href="isRouterTarget(option.href) ? null : option.href"
      :to="isRouterTarget(option.href) ? option.href : null"
      @click.stop="() => option.callback?.(...callbackArgs) ?? null"
    >
      <i v-if="option.icon" :class="`fas fa-${option.icon} dropdown-icon mr-1`" />
      {{ $t(option.textKey) }}
    </b-dropdown-item>
    <slot name="added-content" />
  </b-dropdown>
</template>

<script>
import { isExternalUrl } from '@/helper/urls'

export default {
  props: {
    options: { type: Array, default: () => [] },
    callbackArgs: { type: Array, default: () => [] },
    floatRight: { type: Boolean, default: true },
    variant: { type: String, default: 'dark' },
    icon: { type: String, default: 'ellipsis-v' },
    direction: { type: String, default: 'down' },
    title: { type: String, default: null },
  },
  computed: {
    activeOptions () {
      return this.options.filter(option => !option.hide)
    },
    showOverflowMenu () {
      return this.activeOptions.length ||
        'added-content' in this.$slots // true if the 'added-content' slot is used
    },
  },
  methods: {
    isRouterTarget (href) {
      return !!href && !isExternalUrl(href) && !href.startsWith('#')
    },
  },
}
</script>

<style lang="scss" scoped>
.overflow-menu {
  &.dark-variant {
    color: var(--fs-color-dark);
  }
  &.light-variant {
    color: white;
  }
  ::v-deep .btn {
    padding: 0.25em .75em;
    margin: -.25em 0;
    color: inherit;
    i {
      transition: transform .1s;
    }
    &:hover i {
      transform: scale(1.3);
    }
    &:focus {
      box-shadow: none !important;
    }
  }

  .dropdown-icon {
    width: 1.1em;
  }
}
</style>
