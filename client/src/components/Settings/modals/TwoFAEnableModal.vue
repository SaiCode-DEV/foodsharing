<template>
  <b-modal
    ref="twoFactorEnableModal"
    :title="$t('settings.two_fa_enable.title')"
    :ok-title="okButtonTitle"
    :cancel-title="$t('button.cancel')"
    centered
    size="xl"
    modal-class="bootstrap"
    hide-header-close
    no-close-on-esc
    no-close-on-backdrop
    header-class="d-flex justify-content-center"
    @ok="handleOk"
  >
    <b-tabs
      v-model="activeTab"
      content-class="mt-3"
      no-key-nav
      no-nav-style
      nav-class="nav-justified d-none"
    >
      <!-- Tab 1: Tutorial -->
      <b-tab :title="$t('settings.two_fa_enable.tabs.tutorial')">
        <div class="mb-3">
          <Markdown :source="$t('settings.two_fa_enable.tutorial')" class="mb-3" />
          <Markdown :source="$t('settings.two_fa_enable.explanation')" class="mb-3" />
          <Markdown :source="$t('settings.two_fa_enable.further_info')" class="mb-3" />
        </div>

        <b-button
          v-if="authenticatorApps?.length > 0"
          variant="transparent"
          size="lg"
          class="mb-3 d-flex justify-center"
          @click="recommendedAppsExpanded = !recommendedAppsExpanded"
        >
          {{ $t('settings.two_fa_enable.apps.title') }}
          <i class="fas fa-angle-down fa-animate ml-3" :class="{ 'fa-rotate-180': recommendedAppsExpanded }" />
        </b-button>
        <b-collapse
          v-if="authenticatorApps?.length > 0"
          v-model="recommendedAppsExpanded"
        >
          <p>{{ $t('settings.two_fa_enable.apps.description') }}</p>

          <b-tabs
            v-model="activeOsTab"
            content-class="mt-3"
            class="recommended-apps-tabs"
            pills
          >
            <b-tab
              v-for="category in authenticatorApps"
              :key="category.key"
              :title="category.key"
            >
              <b-row class="ga-2 mx-3">
                <b-col
                  v-for="app in category.apps"
                  :key="app.name"
                  cols="12"
                  md="6"
                  lg="4"
                  class="app-recommendation mb-3"
                >
                  <h6>{{ app.name }}</h6>
                  <div class="d-flex flex-wrap ga-2">
                    <b-button
                      v-for="link in app.links"
                      :key="link.url"
                      :href="link.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      size="sm"
                    >
                      <i :class="link.icon + ' mr-1'" />
                      {{ link.text }}
                    </b-button>
                  </div>
                </b-col>
              </b-row>
            </b-tab>
          </b-tabs>

          <p class="mt-3 text-muted">
            <i class="fas fa-info-circle mr-1" />
            {{ $t('settings.two_fa_enable.apps.info') }}
          </p>
        </b-collapse>
      </b-tab>

      <!-- Tab 2: QR Code -->
      <b-tab :title="$t('settings.two_fa_enable.tabs.qr_code')">
        <h5 class="mb-3">
          {{ $t('settings.two_fa_enable.qrcode.title') }}
        </h5>
        <p>{{ $t('settings.two_fa_enable.qrcode.label') }}</p>
        <p class="text-muted">
          {{ $t('settings.two_fa_enable.qrcode.scan_instruction') }}
        </p>

        <div class="text-center my-4">
          <img
            v-if="modalData.qrCode"
            :src="modalData.qrCode"
            alt="QR Code"
            width="400"
            height="400"
            class="img-fluid testing-qr-code"
          >
        </div>

        <p class="text-muted">
          <i class="fas fa-mobile-alt mr-1" />
          {{ $t('settings.two_fa_enable.qrcode.multi_device') }}
        </p>

        <!-- Collapsible Secret Code -->
        <b-button
          v-b-toggle.secret-collapse
          variant="link"
          class="p-0 mt-3"
        >
          <i class="fas fa-chevron-right mr-1" />
          {{ $t('settings.two_fa_enable.secret_toggle') }}
        </b-button>
        <b-collapse
          id="secret-collapse"
          class="mt-2"
        >
          <p class="mb-2">
            {{ $t('settings.two_fa_enable.secret_label') }}
          </p>
          <div class="text-center p-3 bg-light rounded">
            <code
              class="testing-totp-secret"
              style="font-size: 1.1em;"
            >
              {{ modalData.secret }}
            </code>
          </div>
        </b-collapse>
      </b-tab>

      <!-- Tab 3: Backup Codes -->
      <b-tab :title="$t('settings.two_fa_enable.tabs.backup_codes')">
        <h5 class="mb-3">
          {{ $t('settings.two_fa_enable.backup_codes.title') }}
        </h5>
        <p>{{ $t('settings.two_fa_enable.backup_codes.label') }}</p>

        <b-alert
          variant="warning"
          show
          class="my-3"
        >
          <i class="fas fa-exclamation-triangle mr-1" />
          {{ $t('settings.two_fa_enable.backup_codes.info') }}
        </b-alert>

        <div class="row mb-3">
          <div
            v-for="code in modalData.backupCodes"
            :key="code"
            class="col-sm-3 text-center mb-2 testing-backup-codes"
          >
            <code class="d-block p-2 bg-light rounded">{{ code }}</code>
          </div>
        </div>

        <b-alert
          variant="info"
          show
        >
          <i class="fas fa-save mr-1" />
          {{ $t('settings.two_fa_enable.backup_codes.save_instruction') }}
        </b-alert>

        <b-form-checkbox
          v-model="backupCodesSaved"
          class="mt-3"
        >
          {{ $t('settings.two_fa_enable.backup_codes.confirmation') }}
        </b-form-checkbox>
        <div
          v-if="showBackupCodesError && !backupCodesSaved"
          class="text-danger mt-2"
        >
          <i class="fas fa-exclamation-circle mr-1" />
          {{ $t('settings.two_fa_enable.backup_codes.must_confirm') }}
        </div>
      </b-tab>

      <!-- Tab 4: Confirm Activation -->
      <b-tab :title="$t('settings.two_fa_enable.tabs.confirm')">
        <h5 class="mb-3">
          {{ $t('settings.two_fa_enable.confirm.title') }}
        </h5>
        <p>{{ $t('settings.two_fa_enable.confirm.instruction') }}</p>
        <p class="text-muted">
          {{ $t('settings.two_fa_enable.confirm.test_instruction') }}
        </p>

        <div class="row mt-4">
          <div class="col-md-6 mb-3">
            <div class="mb-2">
              <i class="fas fa-shield-alt mr-1" />
              {{ $t('login.2fa') }}
            </div>
            <totp-field
              id="testing-totp-input-totp"
              v-model="totp"
              :class="{ 'is-invalid': v$.totp.$error }"
              :disabled="isLoading"
            />
            <div
              v-if="v$.totp.$error"
              class="invalid-feedback"
            >
              {{ $t('settings.two_fa_manage.totp_required') }}
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="mb-2">
              <i class="fas fa-key mr-1" />
              {{ $t('login.password') }}
            </div>
            <password-field
              id="testing-totp-input-password"
              v-model="password"
              :invalid="v$.password.$error"
              type="password"
              :disabled="isLoading"
            />
            <div
              v-if="v$.password.$error"
              class="invalid-feedback"
            >
              {{ $t('settings.two_fa_manage.password_required') }}
            </div>
          </div>
        </div>

        <b-alert
          variant="danger"
          show
          class="mt-3"
        >
          <i class="fas fa-exclamation-triangle mr-1" />
          {{ $t('settings.two_fa_enable.danger') }}
        </b-alert>
      </b-tab>
    </b-tabs>
  </b-modal>
</template>

<script setup>
import { ref, computed, defineExpose } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { minLength, maxLength, required } from '@vuelidate/validators'
import totpField from '@/components/Login/TOTPField.vue'
import { pulseError, pulseSuccess } from '@/script'
import { HTTP_RESPONSE } from '@/consts'
import { set2FA, get2FAdata } from '@/api/settings'
import { getTwoFactorApps } from '@/api/twoFactorApps'
import { useUserStore } from '@/stores/user'
import i18n from '@/helper/i18n'

import PasswordField from '@/components/Login/PasswordField.vue'
import Markdown from '@/components/Markdown/Markdown.vue'

const userStore = useUserStore()

const password = ref('')
const totp = ref('')
const isLoading = ref(false)
const twoFactorEnableModal = ref(null)
const activeTab = ref(0)
const backupCodesSaved = ref(false)
const showBackupCodesError = ref(false)
const recommendedAppsExpanded = ref(false)
const activeOsTab = ref(0)

const modalData = ref({
  secret: null,
  qrCode: null,
  backupCodes: [],
})

const authenticatorApps = ref([])

function detectOS () {
  const userAgent = window.navigator.userAgent.toLowerCase()
  const platform = window.navigator.platform.toLowerCase()

  // Check for iOS
  if (/iphone|ipad|ipod/.test(userAgent) || (platform === 'macintel' && navigator.maxTouchPoints > 1)) {
    return 'iOS'
  }

  // Check for Android
  if (/android/.test(userAgent)) {
    return 'Android'
  }

  // Check for Windows
  if (/win/.test(platform)) {
    return 'Windows'
  }

  // Check for Linux
  if (/linux/.test(platform)) {
    return 'Linux'
  }

  // Default to Android if unknown
  return 'Android'
}

async function loadAuthenticatorApps () {
  try {
    const data = await getTwoFactorApps(97)
    if (data && data.categories) {
      authenticatorApps.value = data.categories

      // Auto-select the OS tab based on user's platform
      const detectedOS = detectOS()
      const osIndex = data.categories.findIndex(cat => cat.key === detectedOS)
      if (osIndex !== -1) {
        activeOsTab.value = osIndex
      }
    }
  } catch (e) {
    console.error('Failed to load authenticator apps:', e)
  }
}

const rules = computed(() => ({
  password: { required, minLength: minLength(1) },
  totp: { required, minLength: minLength(6), maxLength: maxLength(6) },
}))

const v$ = useVuelidate(rules, { password, totp })

const okButtonTitle = computed(() => {
  if (activeTab.value === 3) {
    return i18n('settings.two_fa_enable.activate')
  }
  return i18n('settings.two_fa_enable.next')
})

async function show () {
  isLoading.value = true
  activeTab.value = 0
  backupCodesSaved.value = false
  showBackupCodesError.value = false
  password.value = ''
  totp.value = ''

  try {
    loadAuthenticatorApps()
    const data = await get2FAdata()
    modalData.value.secret = data.secret
    modalData.value.qrCode = data.qrCode
    modalData.value.backupCodes = data.backupCodes
    twoFactorEnableModal.value.show()
  } catch (e) {
    pulseError(e.message)
  } finally {
    isLoading.value = false
  }
}

function hide () {
  twoFactorEnableModal.value.hide()
}

async function handleOk (event) {
  // Prevent automatic modal close
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault()
  }

  // If not on the last tab, go to next tab
  if (activeTab.value < 3) {
    // Check if user confirmed backup codes on tab 2
    if (activeTab.value === 2 && !backupCodesSaved.value) {
      showBackupCodesError.value = true
      return
    }
    showBackupCodesError.value = false
    activeTab.value += 1
    return
  }

  // On the last tab, validate and save
  v$.value.$touch()
  if (v$.value.$invalid) {
    return
  }

  isLoading.value = true
  try {
    await set2FA(password.value, totp.value, true)
    isLoading.value = false
    // hide the modal explicitly and then redirect
    twoFactorEnableModal.value.hide()
    await userStore.fetchProfileSettings(true)
    pulseSuccess(i18n('settings.two_fa_enable.success'))
    // Give the user a moment to see the success toast, then reload.
    setTimeout(() => { window.location.reload() }, 2000)
  } catch (e) {
    let message = e.message
    if (e.code === HTTP_RESPONSE.FORBIDDEN) {
      message = i18n('settings.two_fa_enable.failed')
    }
    pulseError(message)
  } finally {
    isLoading.value = false
  }
}

defineExpose({ show, hide })
</script>

<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}

.app-recommendation {
  padding: 1rem;
  border: 1px solid var(--fs-color-success-200);
  border-radius: 0.25rem;
  background-color: var(--fs-color-success-200);

  h6 {
    margin-bottom: 0.75rem;
  }
}

.ga-2 {
  gap: 0.5rem;
}

.fa-animate {
  transition: transform 0.3s ease;
  line-height: unset;
}

::v-deep .nav-pills .nav-link {
  color: var(--fs-color-primary-900);
  &.active {
    background-color: var(--fs-color-secondary-500);
  }
}
</style>
