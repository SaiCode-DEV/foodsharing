<template>
  <div class="my-1">
    <div class="col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" /> {{ $t('register.join_success_message') }}
      </div>
      <b-button
        class="btn btn-primary ml-3 mt-3"
        @click="$emit('load-login')"
      >
        {{ $t('login.login_button_label') }}
      </b-button>
    </div>
  </div>
</template>
