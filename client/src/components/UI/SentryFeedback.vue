<template>
  <div v-if="isVisible">
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
            v-model="feedback.comments"
            rows="3"
            required
            :class="{'is-invalid': v$.comments.$error}"
            @blur="v$.comments.$touch"
          />
          <div v-if="v$.comments.$error" class="invalid-feedback d-block">
            <span v-if="v$.comments.required.$invalid">{{ i18n('feedback.error.message_short') }}</span>
            <span v-else-if="v$.comments.minLength.$invalid">{{ i18n('feedback.error.message_short') }}</span>
          </div>
        </b-form-group>

        <b-form-group>
          <b-button
            variant="secondary"
            block
            :disabled="!!currentFile"
            @click="captureScreenshot"
          >
            {{ i18n('feedback.capture_screenshot') }}
          </b-button>

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

    <b-button
      variant="primary"
      class="feedback-button d-flex align-items-center"
      @click="sentryFeedback.show()"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
        <path
          class="fa-primary"
          d="M215.1 336.9c-1.957 1.957-4.445 3.285-7.156 3.828l-55.55 11.11c-4.898 .9805-9.215-3.34-8.234-8.234l11.11-55.54c.543-2.711 1.875-5.203 3.832-7.156l97.61-97.61l56 56.01L215.1 336.9zM359.8 192.2l-24.48 24.48l-56-56.01l24.48-24.48c10.94-10.93 28.66-10.93 39.6 0l16.41 16.41C370.7 163.6 370.7 181.3 359.8 192.2z"
        />
        <path
          class="fa-secondary"
          d="M256 31.1c-141.4 0-255.1 93.09-255.1 208c0 49.59 21.38 94.1 56.97 130.7c-12.5 50.39-54.31 95.3-54.81 95.8c-2.187 2.297-2.781 5.703-1.5 8.703c1.312 3 4.125 4.797 7.312 4.797c66.31 0 116-31.8 140.6-51.41c32.72 12.31 69.01 19.41 107.4 19.41c141.4 0 255.1-93.09 255.1-207.1S397.4 31.1 256 31.1zM215.1 336.9c-1.957 1.957-4.445 3.285-7.156 3.828l-55.55 11.11c-4.898 .9805-9.215-3.34-8.234-8.234l11.11-55.54c.543-2.711 1.875-5.203 3.832-7.156l97.61-97.61l56 56.01L215.1 336.9zM359.8 192.2l-24.48 24.48l-56-56.01l24.48-24.48c10.94-10.93 28.66-10.93 39.6 0l16.41 16.41C370.7 163.6 370.7 181.3 359.8 192.2z"
        />
      </svg>
      <span>{{ i18n('feedback.button') }}</span>
    </b-button>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, onUnmounted } from 'vue'
import { captureFeedback } from '@/sentry'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'
import serverData from '@/helper/server-data'
import { pulseError, pulseSuccess } from '@/script'
import FileInput from '@/components/UI/FileInput.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email as emailValidator, minLength } from '@vuelidate/validators'

const userStore = useUserStore()
const sentryFeedback = ref(null)
const currentFile = ref(null)
let currentStream = null

const feedback = ref({
  name: '',
  email: '',
  comments: '',
  privacyAccepted: false,
})

// Define validation rules
const rules = computed(() => ({
  email: { required, emailValidator },
  comments: { required, minLength: minLength(6) }, // Ensure more than 5 characters
  privacyAccepted: { required },
}))

// Initialize Vuelidate
const v$ = useVuelidate(rules, feedback)

const isLoggedIn = computed(() => userStore.isLoggedIn)
const isVisible = computed(() => window.location.hostname.includes('beta.foodsharing'))
const isFormValid = computed(() => {
  return feedback.value.name &&
         !v$.value.email.$invalid &&
         !v$.value.comments.$invalid &&
         feedback.value.privacyAccepted
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

  if (currentFile.value?.file) {
    const base64Data = await new Promise((resolve) => {
      const reader = new FileReader()
      reader.onloadend = () => resolve(reader.result.split(',')[1])
      reader.readAsDataURL(currentFile.value.file)
    })
    captureFeedback(feedback.value, [{
      filename: currentFile.value.file.name,
      data: base64Data,
    }])
  } else {
    captureFeedback(feedback.value, [])
  }

  clearFile()
  feedback.value = {
    name: `${userStore.getUserFirstName} ${userStore.getUserLastName} (${userStore.getUserId})`,
    email: userStore.getEmailAddress,
    comments: '',
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
  z-index: 1000;
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
}

.screenshot-preview {
  max-width: 300px;
  margin-top: 1rem;

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
