<template>
  <b-modal
    ref="two_factor_enable_modal"
    :title="$i18n('settings.2fa.title')"
    :ok-title="$i18n('settings.2fa.action_label_enable')"
    :cancel-title="$i18n('button.cancel')"
    centered
    size="lg"
    modal-class="bootstrap"
    hide-header-close
    header-class="d-flex justify-content-center"
    @ok="save"
  >
    <div class="col-sm-auto">
      <!-- Show QR Code -->
      <p
        v-if="qrCode"
        class="mt-3"
      >
        {{ $i18n('settings.2fa.qrcode.label.start') }}
        <a
          :href="$i18n('settings.2fa.qrcode.app.android')"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ $i18n('settings.2fa.qrcode.label.android') }}
        </a>,
        <a
          :href="$i18n('settings.2fa.qrcode.app.ios')"
          target="_blank"
          rel="noopener noreferrer"
        >
          {{ $i18n('settings.2fa.qrcode.label.ios') }}
        </a>
        {{ $i18n('settings.2fa.qrcode.label.end') }}
      </p>
      <img
        v-if="qrCode"
        :src="qrCode"
        alt="QR Code"
        width="400"
        height="400"
        class="img-fluid mx-auto d-block testing-qr-code"
      >
      <!-- Show secret -->
      <p
        v-if="secret"
        class="mt-3"
      >
        {{ $i18n('settings.2fa.secret_label') }}
      </p>
      <div class="text-center">
        <code class="testing-totp-secret">
          {{ secret }}
        </code>
      </div>
      <!-- Show backup codes -->
      <div class="row">
        <div class="col-sm-12 mt-3 mb-3">
          {{ $i18n('settings.2fa.backup_codes_label') }}
        </div>
        <div
          v-for="code in backupCodes"
          :key="code"
          class="col-sm-3 text-center testing-backup-codes"
        >
          <code>{{ code }}</code>
        </div>
        <div class="col-sm-12 mt-3">
          {{ $i18n('settings.2fa.backup_codes_info') }}
        </div>
      </div>
      <p class="mt-3">
        {{ $i18n('settings.2fa.how_to_activate') }}
      </p>
      <div class="row">
        <div class="col-6">
          <div class="mb-1">
            <i class="fas fa-shield-alt mr-1" />
            {{ $i18n('login.2fa') }}
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
            {{ $i18n('settings.2fa.totp_required') }}
          </div>
        </div>
        <div class="col-6">
          <div class="mb-1">
            <i class="fas fa-key mr-1" />
            {{ $i18n('login.password') }}
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
            {{ $i18n('settings.change_password.old_password_required') }}
          </div>
        </div>
      </div>
    </div>
    <b-alert
      variant="danger"
      show
    >
      {{ $i18n('settings.2fa.danger') }}
    </b-alert>
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
  props: {
    secret: {
      type: String,
      default: null,
    },
    qrCode: {
      type: String,
      default: null,
    },
    backupCodes: {
      type: Array,
      default: () => [],
    },
  },
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
      this.$refs.two_factor_enable_modal.show()
    },
    hide () {
      this.$refs.two_factor_enable_modal.hide()
    },
    async save (bvEvt) {
      // prevent automatic modal close; we'll close manually on success
      if (bvEvt && typeof bvEvt.preventDefault === 'function') {
        bvEvt.preventDefault()
      }

      this.isLoading = true
      try {
        await set2FA(this.password, this.totp, true)
        this.isLoading = false
        // hide the modal explicitly and then redirect
        this.$refs.two_factor_enable_modal.hide()
        window.location.href = this.$url('logout')
      } catch (e) {
        let message = e.message
        if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          message = this.$i18n('settings.2fa.activation_failed')
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
