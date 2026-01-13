<template>
  <Container
    :title="$t('settings.email')"
    :collapsible="false"
  >
    <div class="m-3">
      {{ cancel ? $t('settings.email_change.not_changed') : (verified ? $t('settings.email_change.done') : $t('settings.email_change.invalid')) }}
    </div>

    <a class="mx-3 mb-3" :href="$url('dashboard')">{{ $t('settings.email_change.dashboard_link') }}</a>
  </Container>
</template>

<script>

import Container from '@/components/Container/Container.vue'

export default {
  components: { Container },
  props: {
    verified: { type: Boolean, required: true },
    cancel: { type: Boolean, required: true },
  },
}
</script>

<style lang="scss" scoped>
.card.rounded {
    max-width: 500px;
    margin: auto;
    box-shadow: var(--fs-shadow);
  }
</style>
