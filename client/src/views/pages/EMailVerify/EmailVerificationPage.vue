<template>
  <Container
    :title="$i18n('settings.email')"
    :collapsible="false"
  >
    <div class="m-3">
      {{ cancel ? $i18n('settings.changemail.not_changed') : (verified ? $i18n('settings.changemail.done') : $i18n('settings.changemail.invalid')) }}
    </div>

    <a class="mx-3 mb-3" :href="$url('dashboard')">{{ $i18n('settings.changemail.dashboard_link') }}</a>
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
