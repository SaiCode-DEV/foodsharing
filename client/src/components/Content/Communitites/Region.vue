<template>
  <component
    :is="region.hasAmbassador ? 'a' : 'span'"
    v-b-tooltip.right="region.hasAmbassador ? region.emailAddress : $i18n('content.communities.noAmbassador')"
    :href="`mailto:${region.emailAddress}`"
    v-text="region.name"
  />
</template>
<script>
export default {
  props: {
    region: { type: Object, required: true },
  },
}
</script>
<style scoped>
.tooltip::v-deep .tooltip-inner {
  max-width: 30rem;
}
</style>
