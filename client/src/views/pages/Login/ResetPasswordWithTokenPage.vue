<template>
  <div class="card rounded">
    <div class="card-header text-white bg-primary">
      {{ $t('register.set-password') }}
    </div>

    <!-- Password reset form with valid token -->
    <div v-if="resetTokenValid">
      <ResetPasswordForm :reset-token="resetToken" />
    </div>

    <!-- Invalid or expired token -->
    <div v-else-if="!isLoading" class="py-3 px-4">
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle" />
        {{ $t('login.pwreset.expired') }}
      </div>
      <p class="mb-3">
        {{ $t('login.pwreset.request_new') }}
      </p>
      <b-button
        variant="primary"
        @click="goToForgotPassword"
      >
        {{ $t('password.reset') }}
      </b-button>
    </div>

    <!-- Loading state -->
    <div v-else class="py-3 px-4 text-center">
      <i class="fas fa-spinner fa-spin fa-2x" />
      <p class="mt-3">
        {{ $t('globals.loading') }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineProps } from 'vue'
import { validateResetToken } from '@/api/user'
import ResetPasswordForm from '@/components/Login/ResetPasswordForm.vue'
import { url } from '@/helper/urls'

const props = defineProps({
  token: {
    type: String,
    required: true,
    default: null,
  },
})

const resetToken = ref(props.token)
const resetTokenValid = ref(false)
const isLoading = ref(true)

async function validateKey () {
  if (!resetToken.value) {
    resetTokenValid.value = false
    isLoading.value = false
    return
  }

  try {
    resetTokenValid.value = await validateResetToken(resetToken.value)
  } finally {
    isLoading.value = false
  }
}

function goToForgotPassword () {
  window.location.href = url('passwordReset')
}

onMounted(async () => {
  await validateKey()
})
</script>

<style lang="scss" scoped>
.card.rounded {
  max-width: 500px;
  margin: auto;
  box-shadow: var(--fs-shadow);
}

.alert {
  border-radius: 0.25rem;
}
</style>
