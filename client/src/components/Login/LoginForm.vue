<template>
  <div class="py-3 px-4">
    <form @submit.prevent>
      <label class="d-block">
        <div class="mb-1">
          <i class="fas fa-user mr-1" />
          {{ $t('login.email_address') }}
        </div>
        <input
          ref="email"
          v-model="email"
          :placeholder="$t('login.email_address')"
          :aria-label="$t('login.email_address')"
          type="email"
          name="login-email"
          class="testing-login-input-email form-control"
          :class="{ 'is-invalid': v$.email.$invalid }"
          autocomplete="email"
          autofocus
          @focus="focusLogin=true"
        >
        <div
          v-if="v$.email.$invalid"
          class="invalid-feedback"
        >
          <span v-if="!v$.email.required">{{ $t('register.email_required') }}</span>
          <span v-else-if="!v$.email.email">{{ $t('register.email_invalid') }}</span>
        </div>
      </label>
      <label class="d-block">
        <div class="mb-1">
          <i class="fas fa-key mr-1" />
          {{ $t('login.password') }}
        </div>
        <password-field
          id="testing-login-input-password"
          v-model="password"
        />
      </label>
      <label class="d-block">
        <div
          ref="totp1"
          class="mb-1"
          hidden
        >
          <i class="fas fa-shield-alt mr-1" />
          {{ $t('login.2fa') }}
        </div>
        <totp-field
          id="testing-login-input-totp"
          ref="totp2"
          v-model="totp"
          :disabled="isLoading"
          hidden
        />
      </label>
      <label class="d-flex align-items-center mt-3 mb-3">
        <input
          v-model="rememberMe"
          class="testing-login-input-remember mr-2"
          type="checkbox"
          name="login-remember"
        >
        {{ $t('login.steady_login') }}
      </label>
      <b-overlay :show="isLoading">
        <template #overlay>
          <i class="fas fa-spinner fa-spin" />
        </template>
        <b-button
          :aria-label="$t('login.login_button_label')"
          type="submit"
          variant="primary"
          class="testing-login-click-submit btn btn-block"
          :disabled="v$.$invalid"
          @click="submit"
          @keydown.enter="submit"
        >
          <span>
            {{ $t('login.submit_btn') }}
          </span>
          <i class="fas fa-arrow-right mr-auto" />
        </b-button>

        <!-- Passkey Login Option -->
        <div class="text-center my-2">
          <span class="text-muted">{{ $t('login.or') }}</span>
        </div>

        <b-button
          variant="outline-primary"
          class="btn btn-block"
          :disabled="isLoading"
          @click="loginWithPasskey"
        >
          <i class="fas fa-fingerprint mr-2" />
          <span>
            {{ $t('login.passkey_btn') }}
          </span>
        </b-button>
      </b-overlay>
    </form>
  </div>
</template>

<script>
import { isDev } from '@/helper/server-data'
import { login } from '@/api/user'
import { getAuthenticationOptions, verifyAuthentication } from '@/api/passkey'
import { useVuelidate } from '@vuelidate/core'
import { required, email } from '@vuelidate/validators'

import { pulseError } from '@/script'
import { HTTP_RESPONSE } from '@/consts'
import PasswordField from '@/components/Login/PasswordField.vue'
import totpField from '@/components/Login/TOTPField.vue'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'
import { startAuthentication } from '@simplewebauthn/browser'

export default {
  name: 'MenuLogin',
  components: { PasswordField, totpField },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      email: isDev ? 'userbot@example.com' : '',
      password: isDev ? 'user' : '',
      totp: '',
      rememberMe: false,
      isLoading: false,
      error: null,
      focusLogin: false,
    }
  },
  validations: {
    email: { required, email },
    password: { required },
  },
  watch: {
    focusLogin: function (val) {
      if (val) {
        this.$refs.email.focus()
        this.$refs.email.select()
      }
    },
  },
  created () {
    if (localStorage.getItem('login-rememberme')) {
      this.rememberMe = true
    }
  },
  methods: {
    async submit () {
      if (this.rememberMe) {
        localStorage.setItem('login-rememberme', 'true')
      } else {
        localStorage.removeItem('login-rememberme')
      }
      if (!this.email) {
        pulseError(this.$t('login.error_no_email'))
        return
      }
      if (!this.password) {
        pulseError(this.$t('login.error_no_password'))
        return
      }
      this.isLoading = true
      try {
        sessionStorage.clear()
        await login(this.email, this.password, this.totp, this.rememberMe)
        channel.postMessage({ type: BROADCAST_TYPE.LOGIN })
        // Wait a moment to ensure session is written into redis
        await new Promise(resolve => setTimeout(resolve, 250))
        let ref = new URL(location.href).searchParams.get('ref')
        if (!ref?.startsWith('/')) ref = null
        location.replace(ref ?? this.$url('dashboard'))
      } catch (err) {
        this.isLoading = false
        if (err.code && err.code === HTTP_RESPONSE.UNAUTHORIZED) {
          pulseError(this.$t('login.error_no_auth'))
        } else if (err.code && err.code === HTTP_RESPONSE.FORBIDDEN) {
          // Un-hide and focus TOTP field
          console.log('TOTP required')
          this.$refs.totp1.hidden = false
          this.$refs.totp2.$el.hidden = false
          this.$refs.totp2.focus()
          // This is not an error, do not try again
        } else if (err.code && err.code === HTTP_RESPONSE.CONFLICT) {
          document.location.href = this.$url('emailverification')
        } else {
          pulseError(this.$t('error_unexpected'))
          throw err
        }
      }
    },
    async loginWithPasskey () {
      this.isLoading = true
      try {
        // Get authentication options from server (no email needed!)
        // API client already extracts data from response
        const options = await getAuthenticationOptions()

        // Start WebAuthn authentication - browser will show available passkeys
        // Pass options directly, not wrapped in optionsJSON
        const assertion = await startAuthentication(options)

        // Send assertion to server for verification using the passkey API
        await verifyAuthentication(assertion)

        // Login successful
        if (this.rememberMe) {
          localStorage.setItem('login-rememberme', 'true')
        }

        sessionStorage.clear()
        channel.postMessage({ type: BROADCAST_TYPE.LOGIN })
        let ref = new URL(location.href).searchParams.get('ref')
        if (!ref?.startsWith('/')) ref = null
        location.replace(ref ?? this.$url('dashboard'))
      } catch (err) {
        this.isLoading = false
        if (err.name === 'NotAllowedError') {
          pulseError(this.$t('login.passkey_cancelled'))
        } else if (err.name === 'InvalidStateError') {
          pulseError(this.$t('login.passkey_not_found'))
        } else if (err.code && err.code === HTTP_RESPONSE.UNAUTHORIZED) {
          pulseError(this.$t('login.error_no_auth'))
        } else if (err.code && err.code === HTTP_RESPONSE.NOT_FOUND) {
          pulseError(this.$t('login.passkey_not_found'))
        } else {
          pulseError(this.$t('error_unexpected'))
          console.error('Passkey login error:', err)
        }
      }
    },
    focusRef (ref) {
      // Some references may be a component, functional component, or plain element
      // This handles that check before focusing, assuming a `focus()` method exists
      // We do this in a double `$nextTick()` to ensure components have
      // updated & popover positioned first
      this.$nextTick(() => {
        this.$nextTick(() => {
          ;(ref.$el || ref).focus()
        })
      })
    },
  },
}
</script>

<style lang="scss" scoped>
.form-control {
  width: 100%;
}
</style>
