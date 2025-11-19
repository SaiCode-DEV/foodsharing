<template>
  <b-modal
    ref="two_factor_disable_modal"
    :title="$t('settings.2fa.title')"
    :ok-title="$t('settings.2fa.action_label_disable')"
    :cancel-title="$t('button.cancel')"
    centered
    ok-variant="danger"
    size="lg"
    modal-class="bootstrap"
    hide-header-close
    header-class="d-flex justify-content-center"
    @ok="save"
  >
    <div class="col-sm-auto">
      <div class="row">
        <div class="col-12">
          <b-alert
            variant="danger"
            show
          >
            {{ $t('settings.2fa.disable_danger') }}
          </b-alert>
        </div>
        <div class="col-6">
          <div class="mb-1">
            <i class="fas fa-shield-alt mr-1" />
            {{ $t('login.2fa') }}
          </div>
          <!-- TOTP field -->
          <totp-field
            id="testing-totp-input-totp"
            v-model="totp"
            :class="{ 'is-invalid': v$.totp.$error }"
            :disabled="isLoading"
          />
          <div
            v-if="v$.totp.$error"
            class="invalid-feedback"
          >
            {{ $t('settings.2fa.totp_required') }}
          </div>
        </div>
        <div class="col-6">
          <div class="mb-1">
            <i class="fas fa-key mr-1" />
            {{ $t('login.password') }}
          </div>
          <!-- Password -->
          <password-field
            id="testing-totp-input-password"
            v-model="password"
            :class="{ 'is-invalid': v$.password.$error }"
            type="password"
            :disabled="isLoading"
          />
          <div
            v-if="v$.password.$error"
            class="invalid-feedback"
          >
            {{ $t('settings.change_password.old_password_required') }}
          </div>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script>
import { useVuelidate } from '@vuelidate/core'
import { minLength, maxLength, required } from '@vuelidate/validators'
import PasswordField from '@/components/Login/PasswordField.vue'
import totpField from '@/components/Login/TOTPField.vue'
import { pulseError } from '@/script'
import { HTTP_RESPONSE } from '@/consts'
import { set2FA } from '@/api/settings'

export default {
  components: { PasswordField, totpField },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      password: '',
      totp: '',
      isLoading: false,
    }
  },
  validations: {
    password: { required, minLength: minLength(1) },
    totp: { required, minLength: minLength(6), maxLength: maxLength(6) },
  },
  methods: {
    show () {
      this.$refs.two_factor_disable_modal.show()
    },
    hide () {
      this.$refs.two_factor_disable_modal.hide()
    },
    async save () {
      this.isLoading = true
      try {
        await set2FA(this.password, this.totp, false)
        this.isLoading = false
        window.location.href = this.$url('logout')
      } catch (e) {
        let message = e.message
        if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          message = this.$t('settings.2fa.deactivation_failed')
        }
        pulseError(message)
      } finally {
        this.isLoading = false
      }
    },
  },
}

</script>

<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
