<template>
  <component
    :is="region.hasAmbassador ? 'a' : 'span'"
    v-b-tooltip.right="region.hasAmbassador ? $url('mail_foodsharing_network', region.emailAddress) : $i18n('content.communities.noAmbassador')"
    :href="$url('mailto_mail_foodsharing_network', region.emailAddress)"
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
