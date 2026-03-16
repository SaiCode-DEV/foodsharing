<template>
  <form class="my-1">
    <div class="my-2">
      <div class="col-sm-auto">
        <label for="password">
          {{ $t('register.login_passwd1') }}
          <sup>
            <i class="fas fa-asterisk" />
          </sup>
        </label>
        <password-field
          id="password"
          v-model="state.password"
          autocomplete="new-password"
          :invalid="v$.password.$invalid && v$.password.$dirty"
          type="password"
          placeholder="login.password"
          @input="v$.password.$touch()"
        />
        <div v-if="v$.password.$error && v$.password.$dirty" class="invalid-feedback">
          <span v-if="!v$.password.required">{{ $t('register.password_required') }}</span>
          <span v-if="!v$.password.minLength">{{ $t('register.password_minLength') }}</span>
        </div>
      </div>
      <div class="col-sm-auto">
        <label for="confirmPassword">
          {{ $t('register.login_passwd2') }}
          <sup>
            <i class="fas fa-asterisk" />
          </sup>
        </label>
        <password-field
          id="confirmPassword"
          v-model="state.confirmPassword"
          autocomplete="new-password"
          :invalid="v$.confirmPassword.$invalid && v$.confirmPassword.$dirty"
          type="password"
          placeholder="register.login_passwd2"
          @input="v$.confirmPassword.$touch()"
        />
        <div
          v-if="(v$.confirmPassword.$error && v$.confirmPassword.$dirty) || (v$.password.$error && v$.password.$dirty)"
          class="invalid-feedback"
        >
          <ul>
            <li v-if="v$.password.minLength.$invalid && v$.password.$dirty">
              {{ $t('register.password_minLength') }}
            </li>
            <li v-if="v$.password.complexity.$invalid && v$.password.$dirty">
              {{ $t('register.password_must_be_complex') }}
            </li>
            <li v-if="v$.password.isTrimmed.$invalid && v$.password.$dirty">
              {{ $t('register.password_must_be_trimmed') }}
            </li>
            <li v-if="v$.confirmPassword.required.$invalid && v$.confirmPassword.$dirty">
              {{ $t('register.confirmPassword_required') }}
            </li>
            <li v-if="v$.confirmPassword.sameAsPassword.$invalid && v$.confirmPassword.$dirty">
              {{ $t('register.confirmPassword_sameAsPassword') }}
            </li>
          </ul>
        </div>
        <button
          class="btn btn-primary ml-3 mt-3"
          type="submit"
          :disabled="v$.$invalid"
          @click.prevent="submit"
        >
          {{ $t('register.next') }}
        </button>
        <span class="mr-3 d-flex flex-row-reverse">
          {{ $t('register.requiredFields') }}
          <sup>
            <i class="fas fa-asterisk" />
          </sup>
        </span>
      </div>
    </div>
  </form>
</template>

<script setup>
import { reactive, computed, defineProps, defineEmits } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength, sameAs } from '@vuelidate/validators'
import PasswordField from '@/components/Login/PasswordField.vue'

const props = defineProps({
  password: { type: String, default: '' },
})

const emit = defineEmits(['update:password', 'next'])

const state = reactive({
  password: props.password,
  confirmPassword: props.password,
})

const validations = computed(() => ({
  password: { required, minLength: minLength(8), isTrimmed: (value) => value.length === value.trim().length, complexity: (value) => /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value) },
  confirmPassword: { required, sameAsPassword: sameAs(state.password) },
}))

const v$ = useVuelidate(validations, state)

async function submit () {
  // Set password of the parent component
  await v$.value.$validate()
  emit('update:password', state.password)
  emit('next')
}
</script>
<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
