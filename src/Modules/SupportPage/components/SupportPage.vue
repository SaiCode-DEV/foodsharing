<template>
  <Container
    :title="$i18n('support_page.title')"
    :wrap-content="true"
    :collapsible="false"
  >
    <b-alert show variant="info">
      {{ $i18n('support_page.info_teaser') }}
    </b-alert>
    <b-alert
      v-if="isLoggedIn"
      type="info"
      show
    >
      {{ $i18n('support_page.notification_name') }}
    </b-alert>
    <b-form @submit.prevent="submitTicket">
      <b-form-group v-if="!isLoggedIn" :label="$i18n('support_page.firstName')">
        <b-form-input
          v-model="$v.firstNameData.$model"
          required
          :class="{ 'is-invalid': $v.firstNameData.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="$v.firstNameData.$error"
          class="invalid-feedback"
        >
          <span>{{ $i18n('support_page.validation_firstName') }}</span>
        </div>
      </b-form-group>
      <b-form-group :label="$i18n('support_page.subject')">
        <b-form-input
          v-model="$v.subject.$model"
          required
          :class="{ 'is-invalid': $v.subject.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="$v.subject.$error"
          class="invalid-feedback"
        >
          <span>{{ $i18n('support_page.validation_subject') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$i18n('support_page.email')">
        <b-form-input
          v-model="$v.email.$model"
          required
          :disabled="isLoading || isSuccessfullySubmitted"
          :class="{ 'is-invalid': $v.email.$error }"
        />
        <div
          v-if="$v.email.$error"
          class="invalid-feedback"
        >
          <span>{{ $i18n('support_page.validation_email') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$i18n('support_page.body')">
        <b-form-textarea
          v-model="$v.body.$model"
          rows="5"
          required
          :class="{ 'is-invalid': $v.body.$error }"
          :disabled="isLoading || isSuccessfullySubmitted"
        />
        <div
          v-if="$v.body.$error"
          class="invalid-feedback"
        >
          <span>{{ $i18n('support_page.validation_body') }}</span>
        </div>
      </b-form-group>

      <b-form-group :label="$i18n('support_page.attachments')">
        <div class="d-flex align-items-start">
          <b-form-tags
            v-model="attachmentFileNames"
            no-outer-focus
            size="sm"
            class="flex-grow-1 mr-3 attachment-tags"
          >
            <template #default="{ tags, tagVariant, removeTag }">
              <b-input-group>
                <div class="d-inline-block tag-container">
                  <b-form-tag
                    v-for="tag in tags"
                    :key="tag"
                    :title="tag"
                    :variant="tagVariant"
                    class="mr-1 badge-primary"
                    :class="{
                      'bFormTag': !viewIsMobile,
                      'btn-bFormTagMobile': viewIsMobile,
                    }"
                    @remove="removeTag(tag)"
                  >
                    {{ truncateFilename(tag, viewIsMobile ? 24 : 50) }}
                  </b-form-tag>
                </div>
              </b-input-group>
            </template>
          </b-form-tags>

          <div>
            <input
              id="files"
              type="file"
              multiple
              class="hidden"
              @change="storeFiles"
            >
            <label
              for="files"
              :title="$i18n('mailbox.search')"
              class="btn btn-outline-secondary custom-label"
              :disabled="isLoading || isSuccessfullySubmitted"
            >
              <i v-if="viewIsMobile" class="fas fa-paperclip" />
              <span v-else>{{ $i18n('mailbox.search') }}</span>
            </label>
          </div>
        </div>
      </b-form-group>

      <b-alert
        v-if="isSuccessfullySubmitted"
        show
        variant="warning"
      >
        {{ $i18n('support_page.success') }}
      </b-alert>
      <b-button
        type="submit"
        variant="primary"
        :disabled="isLoading || $v.$invalid || isSuccessfullySubmitted"
      >
        {{ $i18n('button.send') }}
      </b-button>
      <b-button
        variant="primary"
        :disabled="isLoading || !isSuccessfullySubmitted"
        @click="clearForm"
      >
        {{ $i18n('support_page.new_request') }}
      </b-button>
    </b-form>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { createTicket } from '@/api/support'
import { useUserStore } from '@/stores/user'
import { required, email } from 'vuelidate/lib/validators'
import { pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { MAX_SUPPORT_TICKET_ATTACHMENT_SIZE } from '@/consts'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import FileUpload from '@/mixins/FileUpload'

const userStore = useUserStore()

export default {
  components: { Container },
  mixins: [MediaQueryMixin, FileUpload],
  data () {
    return {
      isLoading: true,
      email: this.privateEmail,
      subject: '',
      body: '',
      firstNameData: '',
      attachmentFileNames: [],
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
    /**
     * Removes the attached file if the tag in the b-form-tags was removed.
     */
    attachmentFileNames (newFiles, oldFiles) {
      const removedFiles = oldFiles.filter(file => !newFiles.includes(file))
      removedFiles.forEach(file => {
        const index = this.attachmentFileObjects.findIndex(obj => obj.name === file)
        if (index !== -1) {
          this.attachmentFileObjects.splice(index, 1)
        }
      })
    },
  },
  async mounted () {
    if (this.isLoggedIn) {
      await userStore.fetchDetails()
    }
    this.isLoading = false
  },
  methods: {
    truncateFilename (filename, length) {
      const lastDot = filename.lastIndexOf('.')
      if (lastDot === -1) return filename

      const ext = filename.substring(lastDot)
      const name = filename.substring(0, lastDot)

      if (filename.length <= length) return filename

      const truncatedLength = length - ext.length - 3
      return name.slice(0, truncatedLength) + '...' + ext
    },
    storeFiles (event) {
      // Stores files that were selected as attachments. The files will laterbe attached to the request.
      const files = Array.from(event.target.files)
      const filteredFiles = files.filter(file => file.size <= MAX_SUPPORT_TICKET_ATTACHMENT_SIZE)
      filteredFiles.forEach(file => {
        this.attachmentFileNames.push(file.name)
        this.attachmentFileObjects.push(file)
      })

      // Show an error message if any of the selected files were too large
      if (files.length > filteredFiles.length) {
        pulseError(this.$i18n('mailbox.attachment.too_large_to_send'))
      }
    },
    async submitTicket () {
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
        pulseSuccess(this.$i18n('support_page.success'))
      } catch (e) {
        pulseError(i18n('error_unexpected') + ': ' + e.message)
      }
    },
    clearForm () {
      this.body = ''
      this.subject = ''
      this.attachmentFileNames = []
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
</style>
