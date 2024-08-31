<template>
  <div>
    <p class="m1">
      {{ $i18n('settings.change_password.title') }}
    </p>

    <div class="col-sm-auto">
      <password-field
        v-model="$v.oldPassword.$model"
        class="mt-3"
        :class="{ 'is-invalid': $v.oldPassword.$error }"
        type="password"
        placeholder="settings.change_password.old_password_label"
        :disabled="isLoading"
      />
      <div
        v-if="$v.oldPassword.$error"
        class="invalid-feedback"
      >
        {{ $i18n('settings.change_password.old_password_required') }}
      </div>
    </div>

    <div class="col-sm-auto">
      <password-field
        v-model="$v.newPassword.$model"
        class="mt-3"
        :class="{ 'is-invalid': $v.newPassword.$error }"
        type="password"
        placeholder="settings.change_password.new_password_label"
        :disabled="isLoading"
      />
      <div
        v-if="$v.newPassword.$error"
        class="invalid-feedback"
      >
        <span v-if="!$v.newPassword.required || !$v.newPassword.minLength">
          {{ $i18n('settings.change_password.new_password_required') }}
        </span>
        <span v-else-if="!$v.newPassword.isTrimmed">
          {{ $i18n('settings.change_password.new_password_must_be_trimmed') }}
        </span>
      </div>
    </div>

    <div class="col-sm-auto">
      <password-field
        v-model="$v.confirmNewPassword.$model"
        class="mt-3"
        :class="{ 'is-invalid': $v.confirmNewPassword.$error }"
        type="password"
        placeholder="settings.change_password.new_password_confirmation_label"
        :disabled="isLoading"
      />
      <div
        v-if="$v.confirmNewPassword.$error"
        class="invalid-feedback"
      >
        {{ $i18n('settings.change_password.new_password_confirmation_invalid') }}
      </div>
    </div>

    <button
      class="btn btn-primary btn-sm m-2 mt-3"
      :disabled="isLoading || $v.$invalid"
      @click="submitPassword"
      v-text="$i18n('settings.change_password.submit')"
    />
  </div>
</template>

<script>
import { pulseError, pulseInfo } from '@/script'
import { minLength, required, sameAs } from 'vuelidate/lib/validators'
import { requestPasswordChange } from '@/api/settings'
import PasswordField from '@/components/Login/PasswordField.vue'

export default {
  components: { PasswordField },
  data () {
    return {
      isLoading: false,
      oldPassword: '',
      newPassword: '',
      confirmNewPassword: '',
    }
  },
  validations: {
    oldPassword: { required, minLength: minLength(1) },
    newPassword: { required, minLength: minLength(8), isTrimmed: (value) => value.length === value.trim().length },
    confirmNewPassword: { required, sameAsPassword: sameAs('newPassword') },
  },
  methods: {
    async submitPassword () {
      this.isLoading = true

      try {
        await requestPasswordChange(this.oldPassword, this.newPassword)
        pulseInfo(this.$i18n('settings.change_password.success'), { sticky: true })
        this.oldPassword = ''
        this.newPassword = ''
        this.confirmNewPassword = ''
        this.$v.$reset()
      } catch (e) {
        let message = e.message
        if (e.code === 403) {
          message = this.$i18n('settings.changemail.wrong_password')
        }
        pulseError(message)
      }

      this.isLoading = false
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
