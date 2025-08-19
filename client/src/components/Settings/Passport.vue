<template>
  <div>
    <b-alert
      v-if="userStore.isVerified"
      variant="info"
      show
    >
      {{ $i18n('settings.passport.verified_text') }}
    </b-alert>

    <b-alert
      v-if="!userStore.isVerified"
      variant="danger"
      show
    >
      {{ $i18n('settings.passport.non_verified_text') }}
    </b-alert>

    <!-- Alert for never activated passport -->
    <b-alert
      v-if="isNotSetLastPassDate"
      variant="info"
      show
    >
      {{ $i18n('settings.passport.passport_not_activated') }}
      {{ $i18n('settings.passport.ask_your_ambassadors') }}
    </b-alert>

    <!-- Alert for activated passport (either valid or invalid) -->
    <b-alert
      v-else
      :variant="userStore.isPassportInvalid ? 'danger' : userStore.isPassportInvalidSoon ? 'warning' : 'info'"
      show
    >
      <Markdown
        v-if="!userStore.isPassportInvalid"
        :source="passportValidMessage"
      />
      <span v-if="userStore.isPassportInvalid">{{ $i18n('settings.passport.passport_is_invalid') }}</span>
      <span v-if="userStore.isPassportInvalid || userStore.isPassportInvalidSoon">{{ $i18n('settings.passport.ask_your_ambassadors') }}</span>
    </b-alert>

    <div v-if="!isNotSetLastPassDate && !userStore.isPassportInvalid && userStore.isVerified" class="d-flex flex-wrap justify-content-center">
      <CreatePDFButton
        class="m-2"
      />
      <GoogleWalletButton
        class="m-2"
        href="/api/user/current/google/wallet"
      />
      <AppleWalletButton
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
import { onMounted, computed } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import i18n from '@/helper/i18n'
import dateFormatter from '@/helper/date-formatter'

const userStore = useUserStore()

onMounted(async () => {
  await userStore.fetchDetails()
})

const isNotSetLastPassDate = computed(() => {
  return userStore.details.lastPassDate === null || userStore.details.lastPassDate === undefined
},
)

const passportValidMessage = computed(() => {
  return i18n('settings.passport.passport_is_valid_until', {
    days: userStore.details.lastPassUntilValidInDays,
    date: dateFormatter.format(userStore.details.lastPassUntilValid, {
      day: 'numeric',
      month: 'numeric',
      year: 'numeric',
    }),
  })
})
</script>
