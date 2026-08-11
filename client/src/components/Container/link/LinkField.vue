<template>
  <FsLink
    :to="$url(type, entry.id)"
    class="list-group-item list-group-item-action field"
  >
    <h6 class="field-headline" v-text="entry.name" />
  </FsLink>
</template>

<script>
import FsLink from '@/components/UI/FsLink.vue'

export default {
  components: { FsLink },
  props: {
    entry: { type: Object, default: () => {} },
    type: { type: String, default: 'home' },
  },
}
</script>
