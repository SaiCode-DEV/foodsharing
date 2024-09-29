<template>
  <div>
    <div v-if="userStore.isVerified">
      {{ $i18n('settings.passport.verified_text') }}
    </div>
    <div v-else>
      {{ $i18n('settings.passport.non_verified_text') }}
    </div>
    <div class="d-flex flex-wrap justify-content-center">
      <CreatePDFButton
        :disabled="!userStore.isVerified"
        class="m-2"
      />
      <GoogleWalletButton
        v-if="userStore.isVerified"
        class="m-2"
        href="/api/user/current/google/wallet"
      />
      <AppleWalletButton
        v-if="userStore.isVerified"
        class="m-2"
        href="/api/user/current/apple/wallet"
      />
    </div>
  </div>
</template>

<script setup>
import GoogleWalletButton from './GoogleWalletButton.vue'
import AppleWalletButton from './AppleWalletButton.vue'
import CreatePDFButton from './CreatePDFButton.vue'
import { useUserStore } from '@/stores/user.js'
import { onMounted } from 'vue'

const userStore = useUserStore()

onMounted(async () => {
  await userStore.fetchDetails()
})
</script>
