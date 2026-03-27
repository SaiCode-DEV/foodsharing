<template>
  <div v-if="(isBeta || isDev) && serverData.ravenConfig">
    <b-modal
      id="sentry-feedback"
      ref="sentryFeedback"
      :title="i18n('feedback.title')"
      hide-backdrop
      scrollable
      no-close-on-backdrop
      :ok-disabled="!isFormValid"
      :cancel-title="i18n('button.cancel')"
      :ok-title="i18n('button.send')"
      @ok="handleSubmit"
      @show="onShow"
    >
      <b-alert variant="warning" show>
        <Markdown :source="i18n('feedback.no_support')" />
      </b-alert>

      <b-alert
        v-if="!serverData.ravenConfig"
        variant="danger"
        show
      >
        {{ i18n('feedback.error.no_sentry') }}
      </b-alert>
      <b-form v-else>
        <template v-if="!isLoggedIn">
          <b-form-group :label="i18n('support_page.your_name')">
            <b-form-input v-model="feedback.name" required />
          </b-form-group>
        </template>
        <template v-else>
          <b-alert variant="info" show>
            {{ i18n('feedback.name_sent') }}
          </b-alert>
        </template>

        <b-form-group :label="i18n('support_page.email')">
          <b-form-input
            v-model="feedback.email"
            type="email"
            :class="{'is-invalid': v$.email.$error}"
            required
            @blur="v$.email.$touch"
          />
          <div v-if="v$.email.$error" class="invalid-email">
            <span v-if="v$.email.required.$invalid">{{ i18n('register.email_required') }}</span>
            <span v-else-if="v$.email.emailValidator.$invalid">{{ i18n('register.email_invalid') }}</span>
          </div>
        </b-form-group>

        <b-form-group :label="i18n('support_page.body')">
          <b-form-textarea
            v-model="feedback.message"
            rows="3"
            required
            :class="{'is-invalid': v$.message.$error}"
            @blur="v$.message.$touch"
          />
          <div v-if="v$.message.$error" class="invalid-feedback d-block">
            <span v-if="v$.message.required.$invalid">{{ i18n('feedback.error.message_short') }}</span>
            <span v-else-if="v$.message.minLength.$invalid">{{ i18n('feedback.error.message_short') }}</span>
          </div>
        </b-form-group>

        <b-form-group>
          <b-button
            v-if="screenshotSupported"
            variant="secondary"
            block
            :disabled="!!currentFile"
            class="mb-2"
            @click="captureScreenshot"
          >
            {{ i18n('feedback.capture_screenshot') }}
          </b-button>
          <b-alert
            v-else
            variant="secondary"
            show
            class="mb-2"
          >
            {{ i18n('feedback.screenshot_not_supported') }}
          </b-alert>

          <FileInput
            :value="currentFile?.file"
            accept="image/*"
            :disabled="currentFile?.isScreenshot"
            :max-files="1"
            @update:value="handleFileUpload"
          />

          <div v-if="currentFile" class="screenshot-preview">
            <img :src="currentFile.url" class="img-fluid mb-2">
            <b-button
              size="sm"
              variant="danger"
              @click="clearFile"
            >
              {{ i18n('button.delete') }}
            </b-button>
          </div>
        </b-form-group>

        <b-form-group>
          <b-form-checkbox
            v-model="feedback.privacyAccepted"
            :class="{'is-invalid': v$.privacyAccepted.$error}"
            required
            @change="v$.privacyAccepted.$touch"
          >
            <small class="text-muted">{{ i18n('feedback.privacy') }}</small>
          </b-form-checkbox>
        </b-form-group>
      </b-form>
    </b-modal>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, onUnmounted, defineExpose } from 'vue'
import { captureFeedback } from '@/sentry'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'
import serverData from '@/helper/server-data'
import { pulseError, pulseSuccess } from '@/script'
import FileInput from '@/components/UI/FileInput.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email as emailValidator, minLength } from '@vuelidate/validators'
import { useEnvironmentCheck } from '@/composables/useEnvironmentCheck'

const { isBeta, isDev } = useEnvironmentCheck()
const userStore = useUserStore()
const sentryFeedback = ref(null)
const currentFile = ref(null)
let currentStream = null

const feedback = ref({
  name: '',
  email: '',
  message: '',
  privacyAccepted: false,
})

defineExpose({
  show: () => sentryFeedback.value?.show(),
  hide: () => sentryFeedback.value?.hide(),
})

// Define validation rules
const rules = computed(() => ({
  email: { required, emailValidator },
  message: { required, minLength: minLength(6) }, // Ensure more than 5 characters
  privacyAccepted: { required },
}))

// Initialize Vuelidate
const v$ = useVuelidate(rules, feedback)

const isLoggedIn = computed(() => userStore.isLoggedIn)
const isFormValid = computed(() => {
  return feedback.value.name &&
         !v$.value.email.$invalid &&
         !v$.value.message.$invalid &&
         feedback.value.privacyAccepted
})
const screenshotSupported = computed(() => {
  return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia)
})

function onShow () {
  if (isLoggedIn.value) {
    feedback.value.name = `${userStore.getUserFirstName} ${userStore.getUserLastName} (${userStore.getUserId})`
    feedback.value.email = userStore.getEmailAddress
  }
}

onMounted(() => {
  onShow()
})

const captureScreenshot = async () => {
  try {
    const stream = await navigator.mediaDevices.getDisplayMedia({
      preferCurrentTab: true,
      video: { displaySurface: 'window' },
    })
    currentStream = stream
    await sentryFeedback.value.hide()

    const video = document.createElement('video')
    video.srcObject = stream
    await video.play()
    await new Promise(resolve => setTimeout(resolve, 300))

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height)

    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
    const file = new File([blob], 'screenshot.png', { type: 'image/png' })
    const url = URL.createObjectURL(blob)
    currentFile.value = { file, url, isScreenshot: true }

    stream.getTracks().forEach(track => track.stop())
    currentStream = null
  } catch (error) {
    pulseError(i18n('feedback.error.screenshot'))
  } finally {
    sentryFeedback.value.show()
  }
}

const clearFile = () => {
  if (currentFile.value?.url) URL.revokeObjectURL(currentFile.value.url)
  currentFile.value = null
}

const handleFileUpload = (file) => {
  clearFile()
  if (!file) return

  try {
    if (file.type?.startsWith('image/')) {
      const url = URL.createObjectURL(file)
      currentFile.value = {
        file,
        url,
        isScreenshot: false,
      }
    } else {
      pulseError(i18n('feedback.error.invalid_file'))
    }
  } catch (error) {
    console.error('File upload error:', error)
    pulseError(i18n('feedback.error.invalid_file'))
  }
}

const handleSubmit = async () => {
  const isValid = await v$.value.$validate()
  if (!isValid || !isFormValid.value) return

  const attachments = []

  if (currentFile.value?.file) {
    try {
      // Create Uint8Array from file's arrayBuffer
      const data = new Uint8Array(
        await currentFile.value.file.arrayBuffer(),
      )

      attachments.push({
        data,
        filename: currentFile.value.file.name,
      })
    } catch (error) {
      console.error('Error processing attachment:', error)
    }
  }

  const success = await captureFeedback(feedback.value, attachments)
  if (!success) return

  clearFile()
  feedback.value = {
    name: `${userStore.getUserFirstName} ${userStore.getUserLastName} (${userStore.getUserId})`,
    email: userStore.getEmailAddress,
    message: '',
    privacyAccepted: false,
  }
  sentryFeedback.value.hide()
  pulseSuccess(i18n('feedback.success'))
}

onUnmounted(() => {
  if (currentStream) currentStream.getTracks().forEach(track => track.stop())
  clearFile()
})
</script>

<style lang="scss">
.feedback-button {
  position: fixed;
  bottom: 0;
  left: 0;
  z-index: 990;
  margin: 1rem;
  background-color: var(--fs-color-secondary-300) !important;
  color: var(--fs-color-primary-900) !important;

  svg {
    width: 1.5rem;
    .fa-secondary{opacity:.4;fill:var(--fs-color-primary-900)}
    .fa-primary{fill:var(--fs-color-primary-900)}
   }
  span { margin-left: 0.5rem; }

  @media (max-width: 600px) {
    border-radius: 50%;
    aspect-ratio: 1/1;
    span { display: none; }
  }
}

#sentry-feedback {
  .modal-dialog {
    position: fixed;
    bottom: 0;
    left: 0;
    margin: 0.5rem;

    .modal-content {
      border-radius: 0.5rem;
      background-color: var(--fs-color-elevated);
      border: 1px solid var(--fs-color-primary-300);
    }
  }

  .form-group:last-of-type {
    margin-bottom: 0;
  }
}

.screenshot-preview {
  max-width: 300px;
  margin-top: 0.5rem;

  img {
    border: 1px solid var(--fs-color-primary-300);
    border-radius: 4px;
  }
}

.invalid-email {
  display: block !important;
  margin-top: 0.25rem;
  color: var(--fs-color-danger-600);
}
</style>
