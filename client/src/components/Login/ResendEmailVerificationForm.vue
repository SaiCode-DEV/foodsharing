<template>
  <Container
    :title="$t('login.resend_verification.title')"
    wrap-content
    :collapsible="false"
  >
    <form @submit.prevent>
      <b-alert show class="my-3">
        {{ $t('login.resend_verification.hint1') }}<br>
        <Markdown class="mt-2" :source="$t('login.resend_verification.hint2')" />
      </b-alert>
      <label class="d-block">
        <div class="mb-1">
          <i class="fas fa-user mr-1" />
          {{ $t('login.email_address') }}
        </div>
        <input
          ref="email"
          v-model="formData.email"
          :placeholder="$t('login.email_address')"
          :aria-label="$t('login.email_address')"
          type="email"
          name="login-email"
          class="testing-login-input-email form-control"
          :class="{ 'is-invalid': v$.email.$invalid }"
          autocomplete="email"
          autofocus
        >
        <div
          v-if="v$.email.$invalid"
          class="invalid-feedback"
        >
          {{ $t('login.resend_verification.email_invalid') }}
        </div>
      </label>
      <b-overlay :show="isLoading">
        <template #overlay>
          <i class="fas fa-spinner fa-spin" />
        </template>
        <b-button
          :aria-label="$t('login.login_button_label')"
          type="submit"
          variant="primary"
          class="testing-login-click-submit btn"
          :disabled="v$.$invalid"
          @click="submit"
          @keydown.enter="submit"
        >
          {{ $t('login.resend_verification.submit') }}
        </b-button>
      </b-overlay>
    </form>
  </Container>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email as emailValidator } from '@vuelidate/validators'
import Container from '@/components/Container/Container.vue'
import { requestVerificationEmail } from '@/api/emailverification'
import { pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import Markdown from '@/components/Markdown/Markdown.vue'

const isLoading = ref(false)
const formData = ref({
  email: '',
})
const rules = computed(() => ({
  email: { required, emailValidator },
}))
const v$ = useVuelidate(rules, formData)

async function submit () {
  isLoading.value = true
  try {
    await requestVerificationEmail(formData.value.email)
    formData.value.email = ''
    pulseSuccess(i18n('login.resend_verification.success'))
  } catch (error) {
    pulseError(i18n('error_unexpected'))
  } finally {
    isLoading.value = false
  }
}
</script>
