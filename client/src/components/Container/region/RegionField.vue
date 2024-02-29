<template>
  <a
    class="list-group-item list-group-item-action field"
    :href="$url('forum', entry.id)"
  >
    <h6 class="field-headline" v-html="entry.name" />
  </a>
</template>

<script>
export default {
  props: {
    entry: { type: Object, default: () => {} },
  },
}
</script>
