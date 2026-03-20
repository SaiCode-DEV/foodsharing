<template>
  <b-modal
    ref="modal"
    :title="$t('settings.email_change.title')"
    size="lg"
    hide-footer
  >
    <p class="mb-3">
      {{ $t(isMe ? 'settings.email_change.explanation' : 'settings.email_change.explanation_other_user') }}
    </p>

    <b-form @submit.prevent="submitEmail">
      <b-form-group
        :label="$t('settings.email_change.new_email_label')"
        label-for="new-email"
      >
        <b-form-input
          id="new-email"
          v-model="emailValue"
          :class="{ 'is-invalid': v$.email.$invalid && v$.email.$dirty }"
          type="email"
          :placeholder="$t('settings.email_change.new_email_placeholder')"
          :disabled="isLoading"
          @input="v$.email.$touch()"
        />
        <div v-if="v$.email.$invalid && v$.email.$dirty" class="invalid-feedback d-block">
          <span v-if="v$.email.email.$invalid">
            {{ $t('settings.email_change.invalid') }}
          </span>
          <span v-else-if="v$.email.notFoodsharingAddress.$invalid">
            {{ $t('settings.email_change.domain') }}
          </span>
        </div>
      </b-form-group>

      <b-form-group
        :label="$t('settings.email_change.confirm_email_label')"
        label-for="new-email-confirm"
      >
        <b-form-input
          id="new-email-confirm"
          v-model="confirmEmail"
          :class="{ 'is-invalid': v$.confirmEmail.$invalid && v$.confirmEmail.$dirty }"
          type="email"
          :placeholder="$t('settings.email_change.confirm_email_placeholder')"
          :disabled="isLoading"
          @input="v$.confirmEmail.$touch()"
        />
        <div
          v-if="v$.confirmEmail.$invalid && v$.confirmEmail.$dirty"
          class="invalid-feedback d-block"
        >
          <span v-if="v$.confirmEmail.required || v$.confirmEmail.sameAsEmail">
            {{ $t('settings.email_change.confirm_email_required') }}
          </span>
        </div>
      </b-form-group>

      <p v-if="isMe" class="mb-2 text-muted">
        {{ $t('settings.email_change.explanation_password') }}
      </p>

      <b-form-group
        :label="$t('settings.email_change.password_label')"
        label-for="password"
      >
        <password-field
          id="password"
          v-model="password"
          :invalid="(v$.password.$invalid && v$.password.$dirty) || !!passwordError"
          type="password"
          placeholder="settings.email_change.password_placeholder"
          :disabled="isLoading"
          @input="handlePasswordInput"
        />
        <div
          v-if="v$.password.$invalid && v$.password.$dirty"
          class="invalid-feedback d-block"
        >
          {{ $t('settings.email_change.password_required') }}
        </div>
        <div
          v-if="passwordError"
          class="invalid-feedback d-block"
        >
          {{ passwordError }}
        </div>
      </b-form-group>

      <div class="d-flex justify-content-end mt-3">
        <b-button
          variant="secondary"
          class="mr-2"
          @click="modal.hide()"
        >
          {{ $t('settings.email_change.cancel') }}
        </b-button>
        <b-button
          type="submit"
          variant="primary"
          :disabled="isLoading || v$.$invalid"
        >
          {{ $t('settings.email_change.submit') }}
        </b-button>
      </div>
    </b-form>
  </b-modal>
</template>

<script setup>

import { ref, computed, getCurrentInstance, defineProps, defineExpose } from 'vue'
import { pulseError, pulseInfo } from '@/script'
import { useVuelidate } from '@vuelidate/core'
import { email, minLength, required, requiredIf, sameAs } from '@vuelidate/validators'
import { requestEmailChange } from '@/api/settings'
import { isNotFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'
import { useUserStore } from '@/stores/user'
import PasswordField from '@/components/Login/PasswordField.vue'
import i18n from '@/helper/i18n'

const props = defineProps({
  isMe: { type: Boolean, required: true },
  userId: { type: Number, required: true },
})

const userStore = useUserStore()
const instance = getCurrentInstance()

const modal = ref(null)
const isLoading = ref(false)
const emailValue = ref('')
const confirmEmail = ref('')
const password = ref('')
const passwordError = ref('')

const rules = computed(() => ({
  email: {
    required,
    minLength: minLength(1),
    email,
    notFoodsharingAddress: isNotFoodsharingDomain,
  },
  confirmEmail: {
    required,
    minLength: minLength(1),
    email,
    sameAs: sameAs(emailValue),
  },
  password: {
    required: requiredIf(() => props.isMe),
    minLength: minLength(1),
  },
}))

const v$ = useVuelidate(rules, { email: emailValue, confirmEmail, password })

function show () {
  modal.value.show()
}

function hide () {
  modal.value.hide()
}

function handlePasswordInput () {
  passwordError.value = ''
  v$.value.password.$touch()
}

async function submitEmail () {
  let confirmationMessage = props.isMe ? 'settings.email_change.question' : 'settings.email_change.question_other_user'
  confirmationMessage = i18n(confirmationMessage) + ' ' + emailValue.value.trim()

  const confirmed = await instance.proxy.$bvModal.msgBoxConfirm(confirmationMessage, {
    title: i18n('are_you_sure'),
    okTitle: i18n('button.apply'),
    cancelTitle: i18n('button.cancel'),
    centered: true,
  })

  if (!confirmed) return

  isLoading.value = true
  passwordError.value = ''

  try {
    const id = props.isMe ? userStore.getUserId : props.userId
    await requestEmailChange(id, emailValue.value.trim(), password.value)
    pulseInfo(i18n(props.isMe ? 'settings.email_change.sent' : 'settings.email_change.sent_other_user'), { sticky: true })
    modal.value.hide()
  } catch (e) {
    if (e.code === HTTP_RESPONSE.FORBIDDEN) {
      if (props.isMe) {
        passwordError.value = i18n('settings.email_change.wrong_password')
      } else {
        pulseError(i18n('settings.email_change.insufficient_permission'))
      }
    } else if (e.code === HTTP_RESPONSE.BAD_REQUEST) {
      pulseError(i18n('settings.email_change.occupied'))
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
