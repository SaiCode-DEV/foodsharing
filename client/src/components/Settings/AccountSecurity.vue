<template>
  <div>
    <!-- Password and Email Change Buttons -->
    <div class="mb-4">
      <b-button
        v-if="isMe"
        variant="outline-primary"
        class="mr-2"
        @click="changePasswordModal.show()"
      >
        <i class="fas fa-key" /> {{ $t('settings.account_security.change_password') }}
      </b-button>
      <b-button
        variant="outline-primary"
        @click="changeEmailModal.show()"
      >
        <i class="fas fa-envelope" /> {{ $t('settings.account_security.change_email') }}
      </b-button>
    </div>

    <hr class="my-4">

    <!-- Multi-Factor Authentication Section -->
    <div class="mb-4">
      <b-alert
        show
        :variant="profileData?.twoFactorEnabled ? 'success' : 'warning'"
        class="d-flex align-items-center"
      >
        <i
          class="mdi  fa-2x mr-3"
          :class="profileData?.twoFactorEnabled ? 'text-success mdi-lock-check' : 'text-warning mdi-lock-open-alert'"
        />
        <h4 class="mb-0">
          {{ profileData?.twoFactorEnabled ? $t('settings.account_security.mfa_enabled') : $t('settings.account_security.mfa_disabled') }}
        </h4>
      </b-alert>

      <!-- Authenticator App Section -->
      <div class="mb-4">
        <h5>{{ $t('settings.account_security.authenticator_app') }}</h5>
        <p class="text-muted">
          {{ $t('settings.account_security.authenticator_app_description') }}
        </p>
        <div
          v-if="profileData?.twoFactorEnabled && isMe"
          id="testing-num-backup-codes"
          class="mb-2 text-muted small"
        >
          {{ $t('settings.two_fa_manage.num_backup_codes') }}: {{ profileData?.numBackupCodes }}
        </div>
        <b-button
          v-if="!profileData?.twoFactorEnabled && isMe"
          variant="outline-success"
          size="sm"
          @click="twoFAEnableModal.show()"
        >
          <i class="fas fa-shield-alt" /> {{ $t('settings.two_fa_manage.enable_button') }}
        </b-button>
        <b-button
          v-else-if="profileData?.twoFactorEnabled"
          variant="outline-danger"
          size="sm"
          @click="twoFADisableModal.show()"
        >
          <i class="fas fa-shield-alt text-danger" /> {{ $t('settings.two_fa_manage.disable_button') }}
        </b-button>
      </div>
    </div>

    <!-- Change Password Modal -->
    <ChangePasswordModal
      ref="changePasswordModal"
    />

    <!-- Change Email Modal -->
    <ChangeEmailModal
      ref="changeEmailModal"
      :is-me="isMe"
      :user-id="userId"
    />

    <!-- 2FA Enable Modal -->
    <TwoFAEnableModal
      ref="twoFAEnableModal"
    />

    <!-- 2FA Disable Modal -->
    <TwoFADisableModal
      ref="twoFADisableModal"
      :is-me="isMe"
      :profile-data="profileData"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useUserStore } from '@/stores/user'
import ChangeEmailModal from './modals/ChangeEmailModal.vue'
import ChangePasswordModal from './modals/ChangePasswordModal.vue'
import TwoFAEnableModal from './modals/TwoFAEnableModal.vue'
import TwoFADisableModal from './modals/TwoFADisableModal.vue'

const props = defineProps({
  profileData: { type: Object, default: null },
})

const userStore = useUserStore()

const isMe = computed(() => userStore.getUserId === props.profileData?.id)
const userId = computed(() => props.profileData?.id)

const changePasswordModal = ref(null)
const changeEmailModal = ref(null)
const twoFAEnableModal = ref(null)
const twoFADisableModal = ref(null)

</script>

<style scoped>
.small { font-size: 0.85rem }

.other-domain {
  opacity: 0.6;
  background-color: #f8f9fa;
}

.fa-shield-alt {
  color: #28a745;
}

h5 {
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}
</style>
