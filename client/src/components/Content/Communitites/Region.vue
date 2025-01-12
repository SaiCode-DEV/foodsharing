<template>
  <span>
    <a :href="$url('publicRegion', region.id)" v-text="region.name" />
    <a v-if="region.hasAmbassador" :href="$url('mailto_mail_foodsharing_network', region.emailAddress)">
      <i class="fas fa-envelope" />
    </a>
  </span>
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
