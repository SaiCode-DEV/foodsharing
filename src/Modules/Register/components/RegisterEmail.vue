<template>
  <div class="card rounded">
    <div class="card-header text-white bg-primary">
      {{ $t('register.title') }}
    </div>

    <!-- input form -->
    <form
      v-if="!successfullySubmitted"
      class="my-1"
      :class="{disabledLoading: isLoading, 'card-body': true}"
    >
      <div class="col-sm-auto">
        <div v-if="props.showTokenExpiredMessage" class="alert alert-warning">
          <i class="fas fa-exclamation-triangle" /> {{ $t('register.token_invalid_or_expired') }}
        </div>
        <div class="alert alert-info">
          <i class="fas fa-info-circle" />
          {{ $t('register.mail_hint') }}
        </div>
        <label for="email">{{ $t('register.login_email') }}</label>
        <sup>
          <i class="fas fa-asterisk" />
        </sup>
        <input
          id="email"
          v-model="state.email"
          autocomplete="username"
          :class="{ 'is-invalid': v$.email.$error && v$.email.$dirty }"
          type="email"
          name="email"
          class="form-control"
          :placeholder="$t('login.email_address')"
          @input="clearSubmissionErrors"
        >
        <div
          v-if="v$.email.$error || isMailInvalid || isMailAttemptInProgress"
          class="invalid-feedback"
        >
          <span v-if="v$.email.required.$invalid">{{ $t('register.email_required') }}</span>
          <span v-else-if="v$.email.emailValidator.$invalid">{{ $t('register.email_invalid') }}</span>
          <span v-else-if="isMailInvalid">{{ $t('register.error_email_invalid') }}</span>
          <span v-else-if="isMailAttemptInProgress">{{ $t('register.email_in_progress') }}</span>
        </div>
      </div>
      <div class="my-2">
        <div class="col-sm-auto">
          <button
            class="btn btn-primary ml-3 mt-3"
            type="submit"
            :disabled="v$.$invalid || isMailInvalid"
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

    <!-- success message -->
    <div v-else class="my-1">
      <div class="col-sm-auto">
        <div class="alert alert-info">
          <i class="fas fa-info-circle" /> {{ $t('register.join_initialised_message') }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email as emailValidator } from '@vuelidate/validators'
import { initialiseRegistration } from '@/api/user'
import { isNotFoodsharingDomain } from '@/helper/urls'
import { HTTP_RESPONSE } from '@/consts'

const props = defineProps({
  showTokenExpiredMessage: {
    type: Boolean,
    default: false,
  },
})

const state = reactive({
  email: '',
})

const validations = computed(() => ({
  email: { required, emailValidator, foodsharing: isNotFoodsharingDomain },
}))

const v$ = useVuelidate(validations, state)

const isMailInvalid = ref(false)
const isMailAttemptInProgress = ref(false)
const isLoading = ref(false)
const successfullySubmitted = ref(false)

function clearSubmissionErrors () {
  isMailInvalid.value = false
  isMailAttemptInProgress.value = false
}

async function submit () {
  isLoading.value = true
  try {
    await initialiseRegistration(state.email)
    successfullySubmitted.value = true
  } catch (err) {
    if (err.code && err.code === HTTP_RESPONSE.FORBIDDEN) {
      isMailAttemptInProgress.value = true
    } else if (err.code && err.code === HTTP_RESPONSE.BAD_REQUEST) {
      isMailInvalid.value = true
    } else {
      throw err
    }
  } finally {
    isLoading.value = false
  }
}
</script>
<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
