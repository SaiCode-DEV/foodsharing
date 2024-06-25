<template>
  <b-nav-item
    :href="href"
    @click="modal ? $bvModal.show(modal) : null"
  >
    <slot name="icon">
      <i
        v-if="icon"
        class="icon-nav fas"
        :class="icon"
      />
    </slot>
    <slot name="text">
      <span class="nav-text" v-text="title" />
      <span class="sr-only" v-text="title" />
    </slot>
  </b-nav-item>
</template>

<script>
export default {
  props: {
    title: {
      type: String,
      default: '',
    },
    href: {
      type: String,
      default: '#',
    },
    modal: {
      type: String,
      default: undefined,
    },
    icon: {
      type: String,
      default: undefined,
    },
  },
}
</script>
