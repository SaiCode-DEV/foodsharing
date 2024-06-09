<template>
  <b-list-group-item
    :variant="variant"
    button
    class="text-center"
    @click="$emit('click')"
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
  },
}
</script>
