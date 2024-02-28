<template>
  <b-nav-item
    :href="href"
    @click="modal ? $bvModal.show(modal) : null"
  >
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
    badge: {
      type: [String, Number],
      default: 0,
    },
  },
}
</script>
