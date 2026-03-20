<template>
  <div class="card rounded">
    <div class="card-header text-white bg-primary">
      {{ $t('password.reset') }}
    </div>

    <div class="py-3 px-4">
      <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle" />
        {{ $t('password.insertmail') }}
      </div>

      <form @submit.prevent="submit">
        <label class="d-block mb-3">
          <div class="mb-1">
            <i class="fas fa-envelope mr-1" />
            {{ $t('register.login_email') }}
          </div>
          <input
            id="email"
            ref="emailInput"
            v-model="emailValue"
            :placeholder="$t('register.login_email')"
            :aria-label="$t('register.login_email')"
            type="email"
            name="reset-email"
            class="form-control"
            :class="{ 'is-invalid': v$.email.$invalid && v$.email.$dirty }"
            autocomplete="email"
            autofocus
            required
          >
          <div
            v-if="v$.email.$invalid && v$.email.$dirty"
            class="invalid-feedback"
          >
            <span v-if="v$.email.required.$invalid">{{ $t('register.email_required') }}</span>
            <span v-else-if="v$.email.email.$invalid">{{ $t('register.email_invalid') }}</span>
          </div>
        </label>

        <b-overlay :show="isLoading">
          <template #overlay>
            <i class="fas fa-spinner fa-spin" />
          </template>
          <b-button
            type="submit"
            variant="primary"
            class="btn btn-block"
            :disabled="v$.$invalid || isLoading"
          >
            {{ $t('button.send') }}
            <i class="fas fa-arrow-right ml-2" />
          </b-button>
        </b-overlay>
      </form>

      <div
        v-if="resetRequested"
        class="alert alert-success mt-3"
      >
        <i class="fas fa-check-circle" />
        {{ $t('login.pwreset.mailSent') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { requestPasswordReset } from '@/api/user'
import { useVuelidate } from '@vuelidate/core'
import { required, email } from '@vuelidate/validators'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const emailInput = ref(null)
const emailValue = ref('')
const isLoading = ref(false)
const resetRequested = ref(false)

const validations = {
  email: { required, email },
}

const v$ = useVuelidate(validations, { email: emailValue })

async function submit () {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isLoading.value = true
  try {
    await requestPasswordReset(emailValue.value)
    resetRequested.value = true
    emailValue.value = ''
    v$.value.$reset()
  } catch (error) {
    console.error('Password reset request failed:', error)
    pulseError(i18n('error_unexpected'))
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  emailInput.value?.focus()
})
</script>

<style lang="scss" scoped>
.card.rounded {
  max-width: 500px;
  margin: auto;
  box-shadow: var(--fs-shadow);
}

.form-control {
  width: 100%;
}

.alert {
  border-radius: 0.25rem;
}
</style>
