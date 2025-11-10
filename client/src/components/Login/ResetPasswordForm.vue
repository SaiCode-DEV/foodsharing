<template>
  <div class="py-3 px-4">
    <div class="alert alert-info mb-3">
      <i class="fas fa-info-circle" />
      {{ $i18n('register.change-password') }}
    </div>

    <form @submit.prevent="submit">
      <label class="d-block mb-3">
        <div class="mb-1">
          <i class="fas fa-key mr-1" />
          {{ $i18n('register.login_passwd1') }}
        </div>
        <password-field
          id="testing-login-input-password"
          v-model="password"
          :placeholder="$i18n('register.login_passwd1')"
          :class="{ 'is-invalid': shouldShowPasswordErrors }"
          @blur="onPasswordBlur"
        />
        <div
          v-if="shouldShowPasswordErrors"
          class="invalid-feedback"
        >
          <div>
            <span v-if="v$.password.required.$invalid">
              {{ $i18n('register.password_required') }}
            </span>
            <span v-if="v$.password.minLength.$invalid && password">
              {{ $i18n('register.password_minLength') }}
            </span>
            <span v-if="v$.password.complexity.$invalid && password">
              {{ $i18n('register.password_must_be_complex') }}
            </span>
            <span v-if="v$.password.isTrimmed.$invalid && password">
              {{ $i18n('register.password_must_be_trimmed') }}
            </span>
          </div>
        </div>
      </label>

      <label class="d-block mb-3">
        <div class="mb-1">
          <i class="fas fa-key mr-1" />
          {{ $i18n('register.login_passwd2') }}
        </div>
        <password-field
          id="testing-login-input-confirm-password"
          v-model="confirmPassword"
          :placeholder="$i18n('register.login_passwd2')"
          :class="{ 'is-invalid': shouldShowConfirmPasswordErrors }"
          @blur="onConfirmPasswordBlur"
        />
        <div
          v-if="shouldShowConfirmPasswordErrors"
          class="invalid-feedback"
        >
          <div>
            <span v-if="v$.confirmPassword.required.$invalid && !confirmPassword">
              {{ $i18n('register.confirmPassword_required') }}
            </span>
            <span v-if="v$.confirmPassword.sameAsPassword.$invalid && confirmPassword && password">
              {{ $i18n('register.confirmPassword_sameAsPassword') }}
            </span>
          </div>
        </div>
      </label>

      <b-overlay :show="isLoading">
        <template #overlay>
          <i class="fas fa-spinner fa-spin" />
        </template>
        <b-button
          id="password-change-button"
          type="submit"
          variant="primary"
          class="btn btn-block"
          :disabled="v$.$invalid || isLoading"
        >
          {{ $i18n('button.save') }}
          <i class="fas fa-check ml-2" />
        </b-button>
      </b-overlay>
    </form>

    <div
      v-if="resetSuccess"
      class="alert alert-success mt-3"
    >
      <i class="fas fa-check-circle" />
      {{ $i18n('login.pwreset.success') }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineProps } from 'vue'
import { resetPassword } from '@/api/user'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength, sameAs } from '@vuelidate/validators'
import { pulseError } from '@/script'
import PasswordField from '@/components/Login/PasswordField.vue'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'

const props = defineProps({
  resetToken: {
    type: String,
    required: true,
  },
})

const password = ref('')
const confirmPassword = ref('')
const isLoading = ref(false)
const resetSuccess = ref(false)
const passwordBlurred = ref(false)
const confirmPasswordBlurred = ref(false)

const validations = computed(() => ({
  password: {
    required,
    minLength: minLength(8),
    isTrimmed: (value) => value.length === value.trim().length,
    complexity: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value),
  },
  confirmPassword: {
    required,
    sameAsPassword: sameAs(password.value),
  },
}))

const v$ = useVuelidate(validations, { password, confirmPassword })

const shouldShowPasswordErrors = computed(() => {
  return passwordBlurred.value && password.value && v$.value.password.$invalid
})

const shouldShowConfirmPasswordErrors = computed(() => {
  // Show error if the field has lost focus OR if both fields are filled in
  return (confirmPasswordBlurred.value || (password.value && confirmPassword.value)) && v$.value.confirmPassword.$invalid
})

function onPasswordBlur () {
  passwordBlurred.value = true
  v$.value.password.$touch()
}

function onConfirmPasswordBlur () {
  confirmPasswordBlurred.value = true
  v$.value.confirmPassword.$touch()
}

async function submit () {
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isLoading.value = true
  try {
    await resetPassword(props.resetToken, password.value)
    resetSuccess.value = true

    // Redirect to login after a short delay
    setTimeout(() => {
      window.location.href = url('login')
    }, 2000)
  } catch (error) {
    console.error('Password reset failed:', error)
    if (error.response?.status === 400) {
      pulseError(error.response.data.message || i18n('login.pwreset.failed'))
    } else {
      pulseError(i18n('error_unexpected'))
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<style lang="scss" scoped>
.alert {
  border-radius: 0.25rem;
}

.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
