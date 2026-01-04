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

      <!-- Security Keys Section -->
      <div v-if="isMe">
        <h5>{{ $t('settings.account_security.security_keys') }}</h5>
        <p class="text-muted">
          {{ $t('settings.account_security.security_keys_description') }}
        </p>

        <div v-if="!supportsWebAuthn" class="alert alert-warning">
          <i class="fas fa-exclamation-triangle" /> {{ $t('settings.account_security.passkeys_not_supported') }}
        </div>

        <div v-else>
          <!-- Security Keys List -->
          <b-list-group v-if="passkeys.length > 0" class="mb-3">
            <b-list-group-item
              v-for="pk in passkeys"
              :key="pk.id"
              :class="{ 'other-domain': !pk.is_current_domain }"
            >
              <div class="d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center">
                    <i class="fas fa-key text-secondary mr-2" />
                    <strong
                      v-if="editingId !== pk.id"
                      class="passkey-name"
                      :title="pk.name || $t('settings.account_security.unnamed_passkey')"
                    >
                      {{ pk.name || $t('settings.account_security.unnamed_passkey') }}
                    </strong>
                    <b-form-input
                      v-else
                      v-model="editingName"
                      size="sm"
                      class="mr-2"
                      style="max-width: 300px;"
                      :placeholder="$t('settings.account_security.device_name_placeholder')"
                      @keyup.enter="saveRename(pk.id)"
                      @keyup.esc="cancelRename"
                    />
                    <b-button
                      v-if="editingId !== pk.id && pk.is_current_domain"
                      size="sm"
                      variant="link"
                      class="p-0 ml-2"
                      @click="startRename(pk)"
                    >
                      <i class="fas fa-edit" />
                    </b-button>
                    <b-button
                      v-if="editingId === pk.id"
                      size="sm"
                      variant="success"
                      class="ml-2"
                      @click="saveRename(pk.id)"
                    >
                      {{ $t('button.save') }}
                    </b-button>
                    <b-button
                      v-if="editingId === pk.id"
                      size="sm"
                      variant="secondary"
                      class="ml-1"
                      @click="cancelRename"
                    >
                      {{ $t('button.cancel') }}
                    </b-button>
                  </div>
                  <div class="text-muted small mt-1">
                    <span v-if="!pk.is_current_domain" class="badge badge-secondary mr-2">
                      {{ pk.rp_id }}
                    </span>
                    {{ $t('settings.account_security.created') }}: {{ $d(new Date(pk.created_at), 'long') }}
                    <span v-if="pk.last_used_at"> · {{ $t('settings.account_security.last_used') }}: {{ $d(new Date(pk.last_used_at), 'long') }}</span>
                  </div>
                  <div v-if="!pk.is_current_domain" class="text-info small">
                    <i class="fas fa-info-circle" /> {{ $t('settings.account_security.other_domain_info', { domain: pk.rp_id }) }}
                  </div>
                </div>

                <div class="ml-3">
                  <b-button
                    size="sm"
                    variant="danger"
                    @click="removePasskey(pk)"
                  >
                    <i class="fas fa-trash" /> {{ $t('settings.account_security.delete') }}
                  </b-button>
                </div>
              </div>
            </b-list-group-item>
          </b-list-group>
          <div v-else class="alert alert-info">
            <i class="fas fa-info-circle" /> {{ $t('settings.account_security.no_passkeys') }}
          </div>

          <p class="text-muted small mb-2">
            <i class="fas fa-info-circle" /> {{ $t('settings.account_security.domain_info') }}
          </p>

          <b-button
            variant="primary"
            @click="showRegisterModal"
          >
            <i class="fas fa-plus" /> {{ $t('settings.account_security.register_passkey') }}
          </b-button>
        </div>
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

    <!-- Register Passkey Modal -->
    <b-modal
      v-if="isMe"
      v-model="showRegisterDialog"
      :title="$t('settings.account_security.register_modal.title')"
      :ok-title="$t('settings.account_security.register_modal.register')"
      :cancel-title="$t('settings.account_security.register_modal.cancel')"
      @ok="handleRegisterOk"
      @cancel="handleRegisterCancel"
    >
      <b-form-group
        :label="$t('settings.account_security.register_modal.device_name_label')"
        label-for="device-name-input"
        :description="$t('settings.account_security.register_modal.device_name_description')"
      >
        <b-form-input
          id="device-name-input"
          v-model="registerDeviceName"
          :placeholder="$t('settings.account_security.register_modal.device_name_placeholder')"
          @keyup.enter="handleRegisterOk"
        />
      </b-form-group>
    </b-modal>

    <!-- Delete Passkey Confirmation Modal -->
    <b-modal
      v-if="isMe"
      v-model="showDeleteDialog"
      :title="$t('settings.account_security.delete_modal.title')"
      ok-variant="danger"
      :ok-title="$t('settings.account_security.delete_modal.delete')"
      :cancel-title="$t('settings.account_security.delete_modal.cancel')"
      @ok="handleDeleteOk"
      @cancel="handleDeleteCancel"
    >
      <p>{{ $t('settings.account_security.delete_modal.confirmation') }}</p>
      <p v-if="passkeyToDelete" class="text-muted">
        <strong>{{ $t('settings.account_security.delete_modal.device_name') }}: {{ passkeyToDelete.name || $t('settings.account_security.unnamed_passkey') }}</strong>
        <br>
        <small>{{ $t('settings.account_security.created') }}: {{ $d(new Date(passkeyToDelete.created_at)) }}</small>
      </p>
    </b-modal>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { listPasskeys, getRegistrationOptions, verifyRegistration, deletePasskey, renamePasskey } from '@/api/passkey'
import { pulseError, pulseSuccess } from '@/script'
import { useUserStore } from '@/stores/user'
import i18n from '@/helper/i18n'
import { startRegistration, browserSupportsWebAuthn } from '@simplewebauthn/browser'
import ChangeEmailModal from './modals/ChangeEmailModal.vue'
import ChangePasswordModal from './modals/ChangePasswordModal.vue'
import TwoFAEnableModal from './modals/TwoFAEnableModal.vue'
import TwoFADisableModal from './modals/TwoFADisableModal.vue'

const props = defineProps({
  profileData: { type: Object, default: null },
})

const userStore = useUserStore()

const passkeys = ref([])
const supportsWebAuthn = ref(false)
const editingId = ref(null)
const editingName = ref('')
const showRegisterDialog = ref(false)
const registerDeviceName = ref('')
const showDeleteDialog = ref(false)
const passkeyToDelete = ref(null)
const isMe = computed(() => userStore.getUserId === props.profileData?.id)
const userId = computed(() => props.profileData?.id)

const changePasswordModal = ref(null)
const changeEmailModal = ref(null)
const twoFAEnableModal = ref(null)
const twoFADisableModal = ref(null)

async function reload () {
  try {
    const resp = await listPasskeys()
    // API client already extracts data, so resp is the array directly
    passkeys.value = Array.isArray(resp) ? resp : []
  } catch (e) {
    // ignore
  }
}

function showRegisterModal () {
  registerDeviceName.value = ''
  showRegisterDialog.value = true
}

async function handleRegisterOk () {
  try {
    const deviceName = registerDeviceName.value.trim()

    // The API client already extracts data from response, so options is the direct result
    const options = await getRegistrationOptions()
    const attResp = await startRegistration(options)
    await verifyRegistration(attResp, deviceName || undefined)
    pulseSuccess(i18n('settings.account_security.passkey_registered'))
    await reload()
  } catch (err) {
    console.error(err)
    // Provide more specific error messages based on error type
    if (err.name === 'NotAllowedError') {
      pulseError(i18n('settings.account_security.error.operation_cancelled'))
    } else if (err.name === 'InvalidStateError') {
      pulseError(i18n('settings.account_security.error.authenticator_already_registered'))
    } else if (err.name === 'NotSupportedError') {
      pulseError(i18n('settings.account_security.error.webauthn_not_supported'))
    } else if (err.name === 'AbortError') {
      pulseError(i18n('settings.account_security.error.operation_aborted'))
    } else {
      pulseError(i18n('settings.account_security.error.registration_failed', { error: err.jsonContent?.message || err.message || 'Unknown error' }))
    }
  }
}

function handleRegisterCancel () {
  registerDeviceName.value = ''
}

function removePasskey (passkey) {
  passkeyToDelete.value = passkey
  showDeleteDialog.value = true
}

async function handleDeleteOk () {
  if (!passkeyToDelete.value) return

  try {
    await deletePasskey(passkeyToDelete.value.id)
    pulseSuccess(i18n('settings.account_security.passkey_deleted'))
    await reload()
  } catch (err) {
    console.error(err)
    pulseError(i18n('settings.account_security.deletion_failed'))
  } finally {
    passkeyToDelete.value = null
  }
}

function handleDeleteCancel () {
  passkeyToDelete.value = null
}

function startRename (passkey) {
  editingId.value = passkey.id
  editingName.value = passkey.name || ''
}

function cancelRename () {
  editingId.value = null
  editingName.value = ''
}

async function saveRename (id) {
  if (!editingName.value.trim()) {
    pulseError(i18n('settings.account_security.name_required'))
    return
  }

  try {
    await renamePasskey(id, editingName.value.trim())
    pulseSuccess(i18n('settings.account_security.passkey_renamed'))
    editingId.value = null
    editingName.value = ''
    await reload()
  } catch (err) {
    console.error(err)
    pulseError(i18n('settings.account_security.rename_failed'))
  }
}

onMounted(async () => {
  supportsWebAuthn.value = browserSupportsWebAuthn()
  await reload()
})
</script>

<style scoped>
.small { font-size: 0.85rem }

.other-domain {
  opacity: 0.6;
  background-color: #f8f9fa;
}

.passkey-name {
  cursor: default;
}

.fa-shield-alt {
  color: #28a745;
}

h5 {
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}
</style>
