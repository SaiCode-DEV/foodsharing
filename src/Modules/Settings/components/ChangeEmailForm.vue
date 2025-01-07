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
        :class="{ 'is-invalid': v$.email.$error }"
        type="email"
        :placeholder="$i18n('settings.changemail.input_label_email')"
        :disabled="isLoading"
      >
      <div v-if="v$.email.$error" class="invalid-feedback">
        <span v-if="!v$.email.notFoodsharingAddress">
          {{ $i18n('settings.changemail.domain') }}
        </span>
      </div>
    </div>

    <div class="col-sm-auto">
      <input
        id="new-email-confirm"
        v-model="confirmEmail"
        class="form-control mt-3"
        :class="{ 'is-invalid': v$.confirmEmail.$error }"
        type="email"
        :placeholder="$i18n('settings.changemail.input_label_email_confirm')"
        :disabled="isLoading"
      >
      <div
        v-if="v$.confirmEmail.$error"
        class="invalid-feedback"
      >
        <span v-if="!v$.confirmEmail.required || !v$.confirmEmail.sameAsEmail">
          {{ $i18n('settings.changemail.confirm_email_required') }}
        </span>
      </div>
    </div>

    <p v-if="isMe" class="m-1 mt-3">
      {{ $i18n('settings.changemail.explanation_password') }}
    </p>

    <div class="col-sm-auto">
      <input
        v-if="isMe"
        id="password"
        v-model="password"
        class="form-control mt-3"
        :class="{ 'is-invalid': v$.password.$error }"
        type="password"
        :placeholder="$i18n('settings.changemail.input_label_password')"
        :disabled="isLoading"
      >
    </div>

    <button
      class="btn btn-primary btn-sm m-2 mt-3"
      :disabled="v$.$invalid"
      @click="submitEmail"
      v-text="$i18n('settings.email')"
    />
  </div>
</template>

<script>
import { pulseError, pulseInfo } from '@/script'
import { useVuelidate } from '@vuelidate/core'
import { email, minLength, not, required, requiredIf, sameAs } from '@vuelidate/validators'
import { requestEmailChange } from '@/api/settings'
import { isFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
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
  validations: {
    email: { required, minLength: minLength(1), email, notFoodsharingAddress: not(isFoodsharingDomain) },
    confirmEmail: { required, minLength: minLength(1), email, sameAsEmail: sameAs('email') },
    password: {
      required: requiredIf(function () {
        return this.isMe
      }),
      minLength: minLength(1),
    },
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
