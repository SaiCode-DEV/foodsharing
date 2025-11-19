<template>
  <div class="py-3 px-4">
    <div class="alert alert-info mb-3">
      <i class="fas fa-info-circle" />
      {{ $t('register.change-password') }}
    </div>

    <form @submit.prevent="submit">
      <label class="d-block mb-3">
        <div class="mb-1">
          <i class="fas fa-key mr-1" />
          {{ $t('register.login_passwd1') }}
        </div>
        <password-field
          id="testing-reset-input-password"
          v-model="password"
          placeholder="register.login_passwd1"
          :class="{ 'is-invalid': shouldShowPasswordErrors }"
          @blur="onPasswordBlur"
        />
        <div
          v-if="shouldShowPasswordErrors"
          class="invalid-feedback"
        >
          <div>
            <span v-if="v$.password.required.$invalid">
              {{ $t('register.password_required') }}
            </span>
            <span v-if="v$.password.minLength.$invalid && password">
              {{ $t('register.password_minLength') }}
            </span>
            <span v-if="v$.password.complexity.$invalid && password">
              {{ $t('register.password_must_be_complex') }}
            </span>
            <span v-if="v$.password.isTrimmed.$invalid && password">
              {{ $t('register.password_must_be_trimmed') }}
            </span>
          </div>
        </div>
      </label>

      <label class="d-block mb-3">
        <div class="mb-1">
          <i class="fas fa-key mr-1" />
          {{ $t('register.login_passwd2') }}
        </div>
        <password-field
          id="testing-reset-input-confirm-password"
          v-model="confirmPassword"
          placeholder="register.login_passwd2"
          :class="{ 'is-invalid': shouldShowConfirmPasswordErrors }"
          @blur="onConfirmPasswordBlur"
        />
        <div
          v-if="shouldShowConfirmPasswordErrors"
          class="invalid-feedback"
        >
          <div>
            <span v-if="v$.confirmPassword.required.$invalid && !confirmPassword">
              {{ $t('register.confirmPassword_required') }}
            </span>
            <span v-if="v$.confirmPassword.sameAsPassword.$invalid && confirmPassword && password">
              {{ $t('register.confirmPassword_sameAsPassword') }}
            </span>
          </div>
        </div>
      </label>

      <label v-if="showTOTP" class="d-block mb-3">
        <div class="mb-1">
          <i class="fas fa-mobile-alt mr-1" />
          {{ $t('login.2fa') }}
        </div>
        <TOTPField
          id="testing-reset-input-totp"
          v-model="totpCode"
          placeholder="settings.2fa.totp_token"
          :class="{ 'is-invalid': shouldShowTOTPErrors }"
          @blur="v$.totp.$touch"
        />
        <div
          v-if="shouldShowTOTPErrors"
          class="invalid-feedback"
        >
          <div>
            <span v-if="v$.totp && v$.totp.required.$invalid && !totpCode">
              {{ $t('settings.2fa.totp_required') }}
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
          {{ $t('button.save') }}
          <i class="fas fa-check ml-2" />
        </b-button>
      </b-overlay>
    </form>

    <div
      v-if="resetSuccess"
      class="alert alert-success mt-3"
    >
      <i class="fas fa-check-circle" />
      {{ $t('login.pwreset.success') }}
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineProps } from 'vue'
import { resetPassword } from '@/api/user'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength, maxLength, sameAs } from '@vuelidate/validators'
import { pulseError } from '@/script'
import PasswordField from '@/components/Login/PasswordField.vue'
import TOTPField from '@/components/Login/TOTPField.vue'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'

const props = defineProps({
  resetToken: {
    type: String,
    required: true,
  },
})

const password = ref('')
const confirmPassword = ref('')
const totpCode = ref('')
const isLoading = ref(false)
const resetSuccess = ref(false)
const passwordBlurred = ref(false)
const confirmPasswordBlurred = ref(false)
const totpBlurred = ref(false)

const showTOTP = computed(() => {
  try {
    const params = new URLSearchParams(window.location.search)
    const q = params.get('totp')
    return q === 'true' || q === '1' || q === 'yes'
  } catch (e) {
    return false
  }
})

const validations = computed(() => {
  const v = {
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
  }

  if (showTOTP.value) {
    v.totp = {
      minLength: minLength(6),
      maxLength: maxLength(6),
      isNumeric: (value) => /^\d+$/.test(value),
      required,
    }
  }

  return v
})

const v$ = useVuelidate(validations, { password, confirmPassword, totp: totpCode })

const shouldShowPasswordErrors = computed(() => {
  return passwordBlurred.value && password.value && v$.value.password.$invalid
})

const shouldShowConfirmPasswordErrors = computed(() => {
  // Show error if the field has lost focus OR if both fields are filled in
  return (confirmPasswordBlurred.value || (password.value && confirmPassword.value)) && v$.value.confirmPassword.$invalid
})

const shouldShowTOTPErrors = computed(() => {
  return (totpBlurred.value || (password.value && totpCode.value)) && v$.value.totp && v$.value.totp.$invalid
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
    await resetPassword(props.resetToken, password.value, totpCode.value || null)
    resetSuccess.value = true

    // Redirect to login after a short delay
    setTimeout(() => {
      window.location.href = url('login')
    }, 2000)
  } catch (error) {
    console.error('Password reset failed:', error)
    if (error?.code === HTTP_RESPONSE.BAD_REQUEST) {
      pulseError(error.message || i18n('login.pwreset.failed'))
    } else if (error?.code === HTTP_RESPONSE.FORBIDDEN) {
      pulseError(i18n('login.pwreset.token_invalid'))
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
