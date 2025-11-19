<template>
  <div class="card rounded">
    <div class="card-header text-white bg-primary">
      {{ $t('login.form_title') }}
    </div>
    <LoginForm />
    <a
      :href="$url('passwordReset')"
      class="pl-2 m-2 pb-2"
      v-text="$t('login.forgotten_password_label')"
    />
  </div>
</template>

<script>
import LoginForm from '@/components/Login/LoginForm.vue'

export default {
  components: { LoginForm },
}
</script>

<style lang="scss" scoped>
.card.rounded {
    max-width: 500px;
    margin: auto;
    box-shadow: var(--fs-shadow);
  }
</style>
