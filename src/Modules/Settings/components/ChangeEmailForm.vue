<template>
  <div>
    <p class="m-1">
      {{ $i18n(isMe ? 'settings.changemail.explanation' : 'settings.changemail.explanation_other_user') }}
    </p>

    <div class="col-sm-auto">
      <input
        id="new-email"
        v-model="email"
        class="form-control mt-3"
        :class="{ 'is-invalid': v$.email.$invalid && v$.email.$dirty }"
        type="email"
        :placeholder="$i18n('settings.changemail.input_label_email')"
        :disabled="isLoading"
        @input="v$.email.$touch()"
      >
      <div v-if="v$.email.$invalid && v$.email.$dirty" class="invalid-feedback">
        <span v-if="v$.email.email">
          {{ $i18n('settings.changemail.invalid') }}
        </span>
        <span v-else-if="v$.email.notFoodsharingAddress">
          {{ $i18n('settings.changemail.domain') }}
        </span>
      </div>
    </div>

    <div class="col-sm-auto">
      <input
        id="new-email-confirm"
        v-model="confirmEmail"
        class="form-control mt-3"
        :class="{ 'is-invalid': v$.confirmEmail.$invalid && v$.confirmEmail.$dirty }"
        type="email"
        :placeholder="$i18n('settings.changemail.input_label_email_confirm')"
        :disabled="isLoading"
        @input="v$.confirmEmail.$touch()"
      >
      <div
        v-if="v$.confirmEmail.$invalid && v$.confirmEmail.$dirty"
        class="invalid-feedback"
      >
        <span v-if="v$.confirmEmail.required || v$.confirmEmail.sameAsEmail">
          {{ $i18n('settings.changemail.confirm_email_required') }}
        </span>
      </div>
    </div>

    <p v-if="isMe" class="m-1 mt-3">
      {{ $i18n('settings.changemail.explanation_password') }}
    </p>

    <div class="col-sm-auto">
      <password-field
        v-model="password"
        class="mt-3"
        :class="{ 'is-invalid': v$.password.$invalid && v$.password.$dirty }"
        type="password"
        placeholder="settings.changemail.password_label"
        :disabled="isLoading"
        @input="v$.password.$touch()"
      />
      <div
        v-if="v$.password.$invalid && v$.password.$dirty"
        class="invalid-feedback"
      >
        {{ $i18n('settings.changemail.password_required') }}
      </div>
    </div>

    <button
      class="btn btn-sm m-2 mt-3"
      :class="(isLoading || v$.$invalid) ? 'btn-secondary' : 'btn-primary'"
      :disabled="v$.$invalid"
      @click="submitEmail"
      v-text="$i18n('settings.email')"
    />
  </div>
</template>

<script>
import { pulseError, pulseInfo } from '@/script'
import { useVuelidate } from '@vuelidate/core'
import { email, minLength, required, requiredIf, sameAs } from '@vuelidate/validators'
import { requestEmailChange } from '@/api/settings'
import { isNotFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'
import { useUserStore } from '@/stores/user'
import PasswordField from '@/components/Login/PasswordField.vue'

const userStore = useUserStore()

export default {
  components: { PasswordField },
  props: {
    /**
     * The form is visible for users themselves and for orga users. Users can only change their own email address by
     * providing their password.
     */
    isMe: { type: Boolean, required: true },
    /**
     * Id of the profile that is being edited.
     */
    userId: { type: Number, required: true },
  },
  setup () {
    return {
      v$: useVuelidate(),
      userStore,
    }
  },
  data () {
    return {
      isLoading: false,
      email: '',
      confirmEmail: '',
      password: '',
    }
  },
  validations () {
    return {
      email: { required, minLength: minLength(1), email, notFoodsharingAddress: isNotFoodsharingDomain },
      confirmEmail: { required, minLength: minLength(1), email, sameAs: sameAs(this.email) },
      password: {
        required: requiredIf(function () {
          return this.isMe
        }),
        minLength: minLength(1),
      },
    }
  },
  methods: {
    async submitEmail () {
      let confirmationMessage = this.isMe ? 'settings.changemail.question' : 'settings.changemail.question_other_user'
      confirmationMessage = this.$i18n(confirmationMessage) + ' ' + this.email.trim()
      if (!await this.$bvModal.msgBoxConfirm(confirmationMessage, {
        title: this.$i18n('are_you_sure'),
        okTitle: this.$i18n('button.apply'),
        cancelTitle: this.$i18n('button.cancel'),
        centered: true,
      })) return

      this.isLoading = true

      try {
        const id = this.isMe ? this.userStore.getUserId : this.userId
        await requestEmailChange(id, this.email.trim(), this.password)
        pulseInfo(this.$i18n(this.isMe ? 'settings.changemail.sent' : 'settings.changemail.sent_other_user'), { sticky: true })
      } catch (e) {
        let message = e.message
        if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          message = this.$i18n(this.isMe ? 'settings.changemail.wrong_password' : 'settings.changemail.insufficient_permission')
        } else if (e.code === HTTP_RESPONSE.BAD_REQUEST) {
          message = this.$i18n('settings.changemail.occupied')
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
