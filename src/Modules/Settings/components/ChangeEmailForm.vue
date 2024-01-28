<template>
  <!-- eslint-disable vue/max-attributes-per-line -->
  <div>
    <div class="head ui-widget-header">
      {{ $i18n('settings.email') }}
    </div>

    <div class="ui-widget-content corner-bottom margin-bottom ui-padding">
      <p class="m-1">
        {{ $i18n('settings.changemail.explanation') }}
      </p>

      <div class="col-sm-auto">
        <input
          id="new-email"
          v-model="$v.email.$model"
          class="form-control mt-3"
          :class="{ 'is-invalid': $v.email.$error }"
          type="email"
          :placeholder="$i18n('settings.changemail.input_label_email')"
          :disabled="isLoading"
        >
        <div
          v-if="$v.email.$error"
          class="invalid-feedback"
        >
          <span v-if="!$v.email.notFoodsharingAddress">
            {{ $i18n('settings.changemail.domain') }}
          </span>
        </div>
      </div>

      <div class="col-sm-auto">
        <input
          id="new-email-confirm"
          v-model="$v.confirmEmail.$model"
          class="form-control mt-3"
          :class="{ 'is-invalid': $v.confirmEmail.$error }"
          type="email"
          :placeholder="$i18n('settings.changemail.input_label_email_confirm')"
          :disabled="isLoading"
        >
        <div
          v-if="$v.confirmEmail.$error"
          class="invalid-feedback"
        >
          <span v-if="!$v.confirmEmail.required || !$v.confirmEmail.sameAsEmail">
            {{ $i18n('settings.changemail.confirm_email_required') }}
          </span>
        </div>
      </div>

      <p class="m-1 mt-3">
        {{ $i18n('settings.changemail.explanation_password') }}
      </p>

      <div class="col-sm-auto">
        <input
          id="password"
          v-model="$v.password.$model"
          class="form-control mt-3"
          :class="{ 'is-invalid': $v.password.$error }"
          type="password"
          :placeholder="$i18n('settings.changemail.input_label_password')"
          :disabled="isLoading"
        >
      </div>

      <button
        class="btn btn-primary btn-sm m-2 mt-3"
        :disabled="$v.$invalid"
        @click="submitEmail"
        v-text="$i18n('settings.email')"
      />
    </div>
  </div>
</template>

<script>
import { pulseError, pulseInfo } from '@/script'
import { email, minLength, not, required, sameAs } from 'vuelidate/lib/validators'
import { requestEmailChange } from '@/api/settings'
import { isFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'

export default {
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
    password: { required, minLength: minLength(1) },
  },
  methods: {
    async submitEmail () {
      this.isLoading = true

      try {
        await requestEmailChange(this.email.trim(), this.password)
        pulseInfo(this.$i18n('settings.changemail.sent'), { sticky: true })
      } catch (e) {
        let message = e.message
        if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          message = this.$i18n('settings.changemail.wrong_password')
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
