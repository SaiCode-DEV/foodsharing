<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" />
        {{ $i18n('register.mail_hint') }}
      </div>
      <label for="email">{{ $i18n('register.login_email') }}</label>
      <sup>
        <i class="fas fa-asterisk" />
      </sup>
    </div>
    <div class="col-sm-auto">
      <input
        id="email"
        v-model="state.email"
        autocomplete="username"
        :class="{ 'is-invalid': v$.email.$error }"
        type="email"
        name="email"
        class="form-control"
        @blur="update"
      >
      <div
        v-if="v$.email.$error || !isMailValidForRegistration || isMailInvalid"
        class="invalid-feedback"
      >
        <span v-if="v$.email.required.$invalid">{{ $i18n('register.email_required') }}</span>
        <span v-else-if="v$.email.emailValidator.$invalid || !v$.email.foodsharing.$invalid || isMailInvalid">{{ $i18n('register.email_invalid') }}</span>
        <span v-else-if="!isMailValidForRegistration">{{ $i18n('register.error_email_exist') }}</span>
      </div>
    </div>
    <div class="my-2">
      <div class="col-sm-auto">
        <label for="password">
          {{ $i18n('register.login_passwd1') }}
          <sup>
            <i class="fas fa-asterisk" />
          </sup>
        </label>
      </div>
      <div class="col-sm-auto">
        <input
          id="password"
          v-model="state.password"
          autocomplete="new-password"
          :class="{ 'is-invalid': v$.password.$error }"
          type="password"
          name="password"
          class="form-control"
          @input="emit('update:password', $event.target.value)"
        >
        <div v-if="v$.password.$error" class="invalid-feedback">
          <span v-if="!v$.password.required">{{ $i18n('register.password_required') }}</span>
          <span v-if="!v$.password.minLength">{{ $i18n('register.password_minLength') }}</span>
        </div>
      </div>
      <div class="my-1">
        <div class="col-sm-auto">
          <label for="confirmPassword">
            {{ $i18n('register.login_passwd2') }}
            <sup>
              <i class="fas fa-asterisk" />
            </sup>
          </label>
        </div>
        <div class="col-sm-auto">
          <input
            id="confirmPassword"
            v-model="state.confirmPassword"
            autocomplete="new-password"
            :class="{ 'is-invalid': v$.confirmPassword.$error }"
            type="password"
            name="confirmPassword"
            class="form-control"
          >
          <div
            v-if="v$.confirmPassword.$error"
            class="invalid-feedback"
          >
            <span
              v-if="!v$.confirmPassword.required"
            >{{ $i18n('register.confirmPassword_required') }}</span>
            <span
              v-else-if="!v$.confirmPassword.sameAsPassword"
            >{{ $i18n('register.confirmPassword_sameAsPassword') }}</span>
          </div>
        </div>
        <button
          class="btn btn-primary ml-3 mt-3"
          type="submit"
          @click.prevent="redirect()"
        >
          {{ $i18n('register.next') }}
        </button>
        <span class="mr-3 d-flex flex-row-reverse">
          {{ $i18n('register.requiredFields') }}
          <sup>
            <i class="fas fa-asterisk" />
          </sup>
        </span>
      </div>
    </div>
  </form>
</template>

<script setup>
import { ref, reactive, computed, defineEmits, defineProps } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email as emailValidator, minLength, sameAs, not } from '@vuelidate/validators'
import { testRegisterEmail } from '@/api/user'
import { isFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'

const props = defineProps({
  email: { type: String, default: '' },
  password: { type: String, default: '' },
})

const emit = defineEmits(['update:email', 'update:password', 'next'])

const state = reactive({
  email: props.email,
  password: props.password,
  confirmPassword: props.password,
})

const validations = computed(() => ({
  email: { required, emailValidator, foodsharing: not(sameAs(isFoodsharingDomain)) },
  password: { required, minLength: minLength(8) },
  confirmPassword: { required, sameAsPassword: sameAs(state.password) },
}))

const v$ = useVuelidate(validations, state)

const isMailValidForRegistration = ref(true)
const isMailInvalid = ref(false)

async function redirect () {
  await v$.value.$validate()
  if (!v$.value.$invalid && !isMailInvalid.value && isMailValidForRegistration.value) {
    emit('next')
  }
}

async function update ($event) {
  emit('update:email', $event.target.value)
  await v$.value.$validate()
  isMailValidForRegistration.value = false
  isMailInvalid.value = false
  try {
    const MailExist = await testRegisterEmail($event.target.value)
    isMailValidForRegistration.value = MailExist.valid
  } catch (err) {
    if (err.code && err.code === HTTP_RESPONSE.BAD_REQUEST) {
      isMailInvalid.value = true
      return isMailInvalid.value
    } else {
      throw err
    }
  }
  return isMailValidForRegistration.value
}
</script>
<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
