<template>
  <div class="my-1">
    <div class="col-sm-auto">
      <div class="alert alert-success">
        <i class="fas fa-check-circle" /> {{ $t('register.join_success_message') }}
      </div>
      <div v-if="newsletterSubscriptionFailed" class="alert alert-warning">
        <i class="fas fa-exclamation-triangle" /> {{ $t('register.join_newsletter_failed') }}
      </div>
      <b-button
        class="btn btn-primary mt-3"
        @click="$emit('load-login')"
      >
        {{ $t('login.login_button_label') }}
      </b-button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  newsletterSubscriptionFailed: {
    type: Boolean,
    default: false,
  },
})
</script>
