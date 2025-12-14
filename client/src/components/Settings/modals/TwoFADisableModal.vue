<template>
  <b-modal
    ref="twoFactorDisableModal"
    :title="$t('settings.two_fa_disable.title')"
    :ok-title="$t('settings.two_fa_disable.deactivate')"
    :cancel-title="$t('button.cancel')"
    centered
    ok-variant="danger"
    size="lg"
    modal-class="bootstrap"
    hide-header-close
    header-class="d-flex justify-content-center"
    @ok="save"
  >
    <div v-if="isMe" class="col-sm-auto">
      <div class="row">
        <div class="col-12">
          <b-alert
            variant="danger"
            show
          >
            {{ $t('settings.two_fa_disable.danger') }}
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
            {{ $t('settings.two_fa_manage.totp_required') }}
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
            :invalid="v$.password.$error"
            type="password"
            :disabled="isLoading"
          />
          <div
            v-if="v$.password.$error"
            class="invalid-feedback"
          >
            {{ $t('settings.two_fa_manage.password_required') }}
          </div>
        </div>
      </div>
    </div>
    <span v-else>
      {{ $t('settings.two_fa_disable.admin_description', { user: props.profileData.firstName }) }}
    </span>
  </b-modal>
</template>

<script setup>
import { ref, computed, defineExpose, defineProps } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { minLength, maxLength, required } from '@vuelidate/validators'
import PasswordField from '@/components/Login/PasswordField.vue'
import totpField from '@/components/Login/TOTPField.vue'
import { pulseError, pulseSuccess } from '@/script'
import { HTTP_RESPONSE } from '@/consts'
import { set2FA } from '@/api/settings'
import { useUserStore } from '@/stores/user'
import i18n from '@/helper/i18n'

const userStore = useUserStore()

const password = ref('')
const totp = ref('')
const isLoading = ref(false)
const twoFactorDisableModal = ref(null)

const props = defineProps({
  isMe: {
    type: Boolean,
    required: true,
  },
  profileData: {
    type: Object,
    default: null,
  },
})

const rules = computed(() => ({
  password: { required, minLength: minLength(1) },
  totp: { required, minLength: minLength(6), maxLength: maxLength(6) },
}))

const v$ = useVuelidate(rules, { password, totp })

function show () {
  twoFactorDisableModal.value.show()
}

function hide () {
  twoFactorDisableModal.value.hide()
}

async function save () {
  isLoading.value = true
  const targetUserId = props.isMe ? null : props.profileData.id
  try {
    await set2FA(password.value, totp.value, false, targetUserId)
    isLoading.value = false
    pulseSuccess(i18n('settings.two_fa_disable.success'))
    userStore.fetchProfileSettings(true)
  } catch (e) {
    let message = e.message
    if (e.code === HTTP_RESPONSE.FORBIDDEN) {
      message = i18n('settings.two_fa_disable.failed')
    }
    pulseError(message)
  } finally {
    isLoading.value = false
  }
}

defineExpose({ show, hide })
</script>

<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
