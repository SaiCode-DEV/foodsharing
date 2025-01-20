<template>
  <b-dropdown
    v-if="activeOptions.length"
    no-caret
    right
    variant="none"
    class="overflow-menu"
    :class="[floatRight ? 'float-right' : '', `${variant}-variant`]"
  >
    <template #button-content>
      <i :class="`fas fa-${icon}`" />
    </template>
    <b-dropdown-item
      v-for="(option, i) in activeOptions"
      :key="i"
      :href="option.href"
      @click.stop="() => option.callback?.(...callbackArgs) ?? null"
    >
      <i :class="`fas fa-${option.icon} dropdown-icon mr-1`" />
      {{ $i18n(option.textKey) }}
    </b-dropdown-item>
  </b-dropdown>
</template>

<script>
export default {
  props: {
    options: { type: Array, default: () => [] },
    callbackArgs: { type: Array, default: () => [] },
    floatRight: { type: Boolean, default: true },
    variant: { type: String, default: 'dark' },
    icon: { type: String, default: 'ellipsis-v' },
  },
  computed: {
    activeOptions () {
      return this.options.filter(option => !option.hide)
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
