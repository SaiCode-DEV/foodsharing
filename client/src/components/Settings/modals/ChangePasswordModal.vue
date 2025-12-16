<template>
  <b-modal
    ref="modal"
    :title="$t('settings.password_change.title')"
    size="lg"
    hide-footer
  >
    <p class="mb-3">
      {{ $t('settings.password_change.explanation') }}
    </p>

    <b-form @submit.prevent="submitPassword">
      <b-form-group
        :label="$t('settings.password_change.old_password_label')"
        label-for="old-password"
      >
        <password-field
          id="old-password"
          v-model="oldPassword"
          :invalid="(v$.oldPassword.$invalid && v$.oldPassword.$dirty) || !!oldPasswordError"
          type="password"
          placeholder="settings.password_change.old_password_placeholder"
          :disabled="isLoading"
          @input="handleOldPasswordInput"
        />
        <div
          v-if="v$.oldPassword.$invalid && v$.oldPassword.$dirty"
          class="invalid-feedback d-block"
        >
          {{ $t('settings.password_change.old_password_required') }}
        </div>
        <div
          v-if="oldPasswordError"
          class="invalid-feedback d-block"
        >
          {{ oldPasswordError }}
        </div>
      </b-form-group>

      <b-form-group
        :label="$t('settings.password_change.new_password_label')"
        label-for="new-password"
      >
        <password-field
          id="new-password"
          v-model="newPassword"
          :invalid="v$.newPassword.$invalid && v$.newPassword.$dirty"
          type="password"
          placeholder="settings.password_change.new_password_placeholder"
          :disabled="isLoading"
          @input="v$.newPassword.$touch()"
        />
        <div
          v-if="v$.newPassword.$invalid && v$.newPassword.$dirty"
          class="invalid-feedback d-block"
        >
          <ul class="mb-0">
            <li v-if="v$.newPassword.required.$invalid || v$.newPassword.minLength.$invalid">
              {{ $t('settings.password_change.new_password_required') }}
            </li>
            <li v-if="v$.newPassword.complexity.$invalid">
              {{ $t('settings.password_change.new_password_must_be_complex') }}
            </li>
            <li v-if="v$.newPassword.isTrimmed.$invalid">
              {{ $t('settings.password_change.new_password_must_be_trimmed') }}
            </li>
          </ul>
        </div>
      </b-form-group>

      <b-form-group
        :label="$t('settings.password_change.confirm_password_label')"
        label-for="confirm-password"
      >
        <password-field
          id="confirm-password"
          v-model="confirmNewPassword"
          :invalid="v$.confirmNewPassword.$invalid && v$.confirmNewPassword.$dirty"
          type="password"
          placeholder="settings.password_change.confirm_password_placeholder"
          :disabled="isLoading"
          @input="v$.confirmNewPassword.$touch()"
        />
        <div
          v-if="v$.confirmNewPassword.$invalid && v$.confirmNewPassword.$dirty"
          class="invalid-feedback d-block"
        >
          {{ $t('settings.password_change.confirm_password_invalid') }}
        </div>
      </b-form-group>

      <div class="d-flex justify-content-end mt-3">
        <b-button
          variant="secondary"
          class="mr-2"
          @click="modal.hide()"
        >
          {{ $t('settings.password_change.cancel') }}
        </b-button>
        <b-button
          type="submit"
          variant="primary"
          :disabled="isLoading || v$.$invalid"
        >
          {{ $t('settings.password_change.submit') }}
        </b-button>
      </div>
    </b-form>
  </b-modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { pulseError, pulseInfo } from '@/script'
import { useVuelidate } from '@vuelidate/core'
import { minLength, required, sameAs } from '@vuelidate/validators'
import { requestPasswordChange } from '@/api/settings'
import PasswordField from '@/components/Login/PasswordField.vue'
import { HTTP_RESPONSE } from '@/consts'
import i18n from '@/helper/i18n'

const modal = ref(null)
const isLoading = ref(false)
const oldPassword = ref('')
const newPassword = ref('')
const confirmNewPassword = ref('')
const oldPasswordError = ref('')

const rules = computed(() => ({
  oldPassword: { required, minLength: minLength(1) },
  newPassword: {
    required,
    minLength: minLength(8),
    isTrimmed: (value) => value.length === value.trim().length,
    complexity: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value),
  },
  confirmNewPassword: { required, sameAs: sameAs(newPassword) },
}))

const v$ = useVuelidate(rules, { oldPassword, newPassword, confirmNewPassword })

function show () {
  modal.value.show()
}

function hide () {
  modal.value.hide()
}

function handleOldPasswordInput () {
  oldPasswordError.value = ''
  v$.value.oldPassword.$touch()
}

async function submitPassword () {
  isLoading.value = true
  oldPasswordError.value = ''

  try {
    await requestPasswordChange(oldPassword.value, newPassword.value)
    pulseInfo(i18n('settings.password_change.success'), { sticky: true })
    oldPassword.value = ''
    newPassword.value = ''
    confirmNewPassword.value = ''
    v$.value.$reset()
    modal.value.hide()
  } catch (e) {
    if (e.code === HTTP_RESPONSE.FORBIDDEN) {
      oldPasswordError.value = i18n('settings.password_change.wrong_password')
    } else {
      pulseError(e.message)
    }
  }

  isLoading.value = false
}

defineExpose({ show, hide })
</script>

<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
}
</style>
