<template>
  <Container
    :title="$t('support_page.title')"
    :wrap-content="true"
    :collapsible="false"
  >
    <b-alert show variant="info">
      {{ $t('support_page.info_teaser') }}
    </b-alert>
    <b-alert
      v-if="isLoggedIn"
      type="info"
      show
    >
      {{ $t('support_page.notification_name') }}
    </b-alert>
    <b-form @submit.prevent="submitTicket">
      <b-form-group v-if="!isLoggedIn" :label="$t('support_page.firstName')">
        <b-form-input
          v-model="v$.firstNameData.$model"
          required
          :class="{ 'is-invalid': v$.firstNameData.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="v$.firstNameData.$error"
          class="invalid-feedback"
        >
          <span>{{ $t('support_page.validation_firstName') }}</span>
        </div>
      </b-form-group>
      <b-form-group :label="$t('support_page.subject')">
        <b-form-input
          v-model="v$.subject.$model"
          required
          :class="{ 'is-invalid': v$.subject.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="v$.subject.$error"
          class="invalid-feedback"
        >
          <span>{{ $t('support_page.validation_subject') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$t('support_page.email')">
        <b-form-input
          v-model="v$.email.$model"
          required
          :disabled="isLoading || isSuccessfullySubmitted"
          :class="{ 'is-invalid': v$.email.$error }"
        />
        <div
          v-if="v$.email.$error"
          class="invalid-feedback"
        >
          <span>{{ $t('support_page.validation_email') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$t('support_page.body')">
        <b-form-textarea
          v-model="v$.body.$model"
          rows="5"
          required
          :class="{ 'is-invalid': v$.body.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="v$.body.$error"
          class="invalid-feedback"
        >
          <span>{{ $t('support_page.validation_body') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$t('support_page.attachment.max_size')">
        <div class="d-flex align-items-start">
          <FileInput
            :value="attachmentFileObjects"
            :disabled="isLoading || isSuccessfullySubmitted"
            :max-files="MAX_SUPPORT_TICKET_ATTACHMENT_FILES"
            :max-file-size="MAX_SUPPORT_TICKET_ATTACHMENT_SIZE"
            @update:value="attachmentFileObjects = $event"
          />
        </div>
      </b-form-group>

      <b-alert
        v-if="isSuccessfullySubmitted"
        show
        variant="warning"
      >
        {{ $t('support_page.success') }}
      </b-alert>
      <div class="d-flex justify-content-end">
        <b-button
          v-if="isSuccessfullySubmitted"
          variant="danger"
          class="mr-2"
          :disabled="isLoading || !isSuccessfullySubmitted"
          @click="clearForm"
        >
          <i class="fas fa-redo" />
          {{ $t('support_page.new_request') }}
        </b-button>
        <b-button
          type="submit"
          variant="primary"
          :disabled="isLoading || v$.$invalid || isSuccessfullySubmitted"
        >
          {{ $t('button.send') }}
        </b-button>
      </div>
    </b-form>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import FileInput from '@/components/UI/FileInput.vue'
import { createTicket } from '@/api/support'
import { useUserStore } from '@/stores/user'
import { useVuelidate } from '@vuelidate/core'
import { required, email } from '@vuelidate/validators'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { MAX_SUPPORT_TICKET_ATTACHMENT_SIZE, MAX_SUPPORT_TICKET_ATTACHMENT_FILES } from '@/consts'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import FileUpload from '@/mixins/FileUpload'

const userStore = useUserStore()

export default {
  components: { Container, FileInput },
  mixins: [MediaQueryMixin, FileUpload],
  setup () {
    return {
      v$: useVuelidate(),
      MAX_SUPPORT_TICKET_ATTACHMENT_SIZE,
      MAX_SUPPORT_TICKET_ATTACHMENT_FILES,
    }
  },
  data () {
    return {
      isLoading: true,
      email: this.privateEmail,
      subject: '',
      body: '',
      firstNameData: '',
      attachmentFileObjects: [],
      isSuccessfullySubmitted: false,
    }
  },
  computed: {
    isLoggedIn: () => userStore.isLoggedIn,
    privateEmail: () => userStore.getEmailAddress ?? '',
    firstName () {
      return userStore.isLoggedIn ? (userStore.getUserFirstName ?? '') : this.firstNameData
    },
  },
  validations: {
    firstNameData: { requiredIfNotLoggedIn: () => { return userStore.isLoggedIn || required } },
    email: { required, email },
    subject: { required },
    body: { required },
  },
  watch: {
    privateEmail (newEmail) {
      if (newEmail) {
        this.email = newEmail
      }
    },
  },
  async mounted () {
    if (this.isLoggedIn) {
      await userStore.fetchDetails()
    }
    this.isLoading = false
  },
  methods: {
    formatFilenames (files, length = 10) {
      return files.length === 1 ? files[0].name : `${files.length} files selected`
    },
    storeFiles (files) {
      this.attachmentFileObjects = files
    },
    async submitTicket () {
      if (this.isLoading || this.isSuccessfullySubmitted || this.v$.$invalid) return
      showLoader()
      this.isLoading = true
      try {
        // Load the attachment files into memory
        const loadFilePromises = this.attachmentFileObjects.map(this.loadFile)
        const loadedFiles = await Promise.all(loadFilePromises)
        const attachments = loadedFiles.map(file => {
          return {
            fileName: file.name,
            contentType: file.type,
            content: file.content,
          }
        })

        // Send the request
        await createTicket(this.email, this.subject, this.body, this.firstName, attachments)
        // this.clearForm()
        this.isSuccessfullySubmitted = true
        pulseSuccess(this.$t('support_page.success'))
      } catch (e) {
        pulseError(i18n('error_unexpected') + ': ' + e.message)
      } finally {
        hideLoader()
        this.isLoading = false
      }
    },
    clearForm () {
      this.body = ''
      this.subject = ''
      this.attachmentFileObjects = []
      this.isSuccessfullySubmitted = false
    },
  },
}
</script>

<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}

.attachment-tags {
  min-height: 40px;
}

.tag-container {
  font-size: 1.5rem;
}

.remove-attachment {
  z-index: 10;
  position: relative;
  pointer-events: all;
}
</style>
