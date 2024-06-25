<template>
  <a
    class="list-group-item list-group-item-action field"
    :href="$url(type, entry.id)"
  >
    <h6 class="field-headline" v-text="entry.name" />
  </a>
</template>

<script>
export default {
  props: {
    entry: { type: Object, default: () => {} },
    type: { type: String, default: 'home' },
  },
}
</script>
