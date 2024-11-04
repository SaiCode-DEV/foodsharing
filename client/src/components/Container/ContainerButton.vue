<template>
  <b-list-group-item
    :variant="variant"
    button
    class="text-center"
    @click="onClick"
  >
    <slot>
      <i v-if="icon" :class="icon + ' mr-2'" />
      <span
        v-if="textKey"
        class="small font-weight-bold"
        v-text="$i18n(textKey)"
      />
    </slot>
  </b-list-group-item>
</template>
<script>
export default {
  props: {
    variant: { type: String, default: '' },
    textKey: { type: String, default: '' },
    icon: { type: String, default: '' },
    href: { type: String, default: null },
  },
  methods: {
    onClick () {
      this.$emit('click')
      if (this.href) {
        location.href = this.href
      }
    },
  },
}
</script>
