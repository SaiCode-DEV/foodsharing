<template>
  <div>
    <p class="m1">
      {{ $i18n('settings.change_password.title') }}
    </p>

    <div class="col-sm-auto">
      <password-field
        v-model="oldPassword"
        class="mt-3"
        :class="{ 'is-invalid': v$.oldPassword.$invalid && v$.oldPassword.$dirty }"
        type="password"
        placeholder="settings.change_password.old_password_label"
        :disabled="isLoading"
        @input="v$.oldPassword.$touch()"
      />
      <div
        v-if="v$.oldPassword.$invalid && v$.oldPassword.$dirty"
        class="invalid-feedback"
      >
        {{ $i18n('settings.change_password.old_password_required') }}
      </div>
    </div>

    <div class="col-sm-auto">
      <password-field
        v-model="newPassword"
        class="mt-3"
        :class="{ 'is-invalid': v$.newPassword.$invalid && v$.newPassword.$dirty }"
        type="password"
        placeholder="settings.change_password.new_password_label"
        :disabled="isLoading"
        @input="v$.newPassword.$touch()"
      />
      <div
        v-if="v$.newPassword.$invalid && v$.newPassword.$dirty"
        class="invalid-feedback"
      >
        <ul>
          <li v-if="v$.newPassword.required.$invalid || v$.newPassword.minLength.$invalid">
            {{ $i18n('settings.change_password.new_password_required') }}
          </li>
          <li v-if="v$.newPassword.complexity.$invalid">
            {{ $i18n('settings.change_password.new_password_must_be_complex') }}
          </li>
          <li v-if="v$.newPassword.isTrimmed.$invalid">
            {{ $i18n('settings.change_password.new_password_must_be_trimmed') }}
          </li>
        </ul>
      </div>
    </div>

    <div class="col-sm-auto">
      <password-field
        v-model="confirmNewPassword"
        class="mt-3"
        :class="{ 'is-invalid': v$.confirmNewPassword.$invalid && v$.confirmNewPassword.$dirty }"
        type="password"
        placeholder="settings.change_password.new_password_confirmation_label"
        :disabled="isLoading"
        @input="v$.confirmNewPassword.$touch()"
      />
      <div
        v-if="v$.confirmNewPassword.$invalid && v$.confirmNewPassword.$dirty"
        class="invalid-feedback"
      >
        {{ $i18n('settings.change_password.new_password_confirmation_invalid') }}
      </div>
    </div>

    <button
      class="btn btn-sm m-2 mt-3"
      :class="(isLoading || v$.$invalid) ? 'btn-secondary' : 'btn-primary'"
      :disabled="isLoading || v$.$invalid"
      @click="submitPassword"
      v-text="$i18n('settings.change_password.submit')"
    />
  </div>
</template>

<script>
import { pulseError, pulseInfo } from '@/script'
import { useVuelidate } from '@vuelidate/core'
import { minLength, required, sameAs } from '@vuelidate/validators'
import { requestPasswordChange } from '@/api/settings'
import PasswordField from '@/components/Login/PasswordField.vue'

export default {
  components: { PasswordField },
  setup () {
    return {
      v$: useVuelidate(),
    }
  },
  data () {
    return {
      isLoading: false,
      oldPassword: '',
      newPassword: '',
      confirmNewPassword: '',
    }
  },
  validations () {
    return {
      oldPassword: { required, minLength: minLength(1) },
      newPassword: { required, minLength: minLength(8), isTrimmed: (value) => value.length === value.trim().length, complexity: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value) },
      confirmNewPassword: { required, sameAs: sameAs(this.newPassword) },
    }
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
        this.v$.$reset()
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
