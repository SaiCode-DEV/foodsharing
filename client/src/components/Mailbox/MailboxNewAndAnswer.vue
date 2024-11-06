<template>
  <Container
    :tag="showTitel"
    :title="showTitel"
    :toggle-visibility="true"
  >
    <address-book
      ref="addressBook"
      :marked-as-selected="emailTo"
      @email-selected="selectEmailFromAdressbock"
    />
    <div class="card bg-white">
      <b-row class="p-2">
        <b-col
          cols="12"
          md="2"
        >
          {{ $i18n('mailbox.sender') }}
        </b-col>
        <b-col
          cols="12"
          md="10"
        >
          <div>
            <b-form-select
              v-model="selectedMailbox[0]"
            >
              <b-form-select-option
                v-for="value in mailboxes"
                :key="value.id"
                :value="value.id"
              >
                {{ value.name }}
              </b-form-select-option>
            </b-form-select>
          </div>
        </b-col>
      </b-row>
      <b-row class="p-2">
        <b-col
          cols="12"
          md="2"
        >
          {{ $i18n('mailbox.recipient') }}
        </b-col>
        <b-col
          cols="12"
          md="10"
        >
          <b-form-tags
            v-model="emailTo"
            no-outer-focus
            :limit="100"
            size="sm"
            class="mb-2"
            no-add-on-enter
            seperator=""
          >
            <template #default="{ tags, inputAttrs, inputHandlers, tagVariant, removeTag }">
              <b-input-group class="mb-2">
                <b-form-input
                  v-model="currentEmailInput"
                  v-bind="inputAttrs"
                  :placeholder="$i18n('mailbox.tag_recipient_hint')"
                  class="form-control"
                  v-on="inputHandlers"
                  @keydown.enter.prevent="addEmailTag(inputAttrs.value)"
                />
                <b-input-group-append>
                  <b-button
                    v-if="!isMobile"
                    variant="outline-primary"
                    @click="addEmailTag(inputAttrs.value)"
                  >
                    {{ $i18n('mailbox.add') }}
                  </b-button>
                  <b-button
                    v-else
                    variant="outline-primary"
                    @click="addEmailTag(inputAttrs.value)"
                  >
                    +
                  </b-button>
                </b-input-group-append>
                <b-input-group-append>
                  <b-button
                    v-if="!isMobile"
                    variant="outline-primary"
                    @click="openAddressbook"
                  >
                    {{ $i18n('mailbox.addressbook') }}
                  </b-button>
                  <b-button
                    v-else
                    variant="outline-primary"
                    @click="openAddressbook"
                  >
                    <i class="far fa-address-book" />
                  </b-button>
                </b-input-group-append>
              </b-input-group>
              <div
                class="d-inline-block"
                style="font-size: 1.5rem;"
              >
                <b-form-tag
                  v-for="tag in tags"
                  :key="tag"
                  :title="tag"
                  :variant="tagVariant"
                  class="mr-1 badge-primary"
                  @remove="removeTag(tag)"
                >
                  {{ tag }}
                </b-form-tag>
              </div>
            </template>
          </b-form-tags>
        </b-col>
      </b-row>

      <b-row class="p-2">
        <b-col
          cols="12"
          md="2"
        >
          {{ $i18n('mailbox.subject') }}
        </b-col>
        <b-col
          cols="12"
          md="10"
        >
          <b-form-input
            v-model="subject"
          />
        </b-col>
      </b-row>

      <b-row class="p-2">
        <b-col md="2">
          {{ $i18n('mailbox.attachment.attach') }}
        </b-col>
        <b-col md="10">
          <b-alert
            v-if="showForwardAttachmentWarning"
            variant="danger"
            show
          >
            {{ $i18n('mailbox.forward_attachment_warning') }}
          </b-alert>
          <div class="flex-container">
            <b-form-tags
              v-model="attachmentFilesName"
              no-outer-focus
              size="sm"
              class="mb-2"
            >
              <template #default="{ tags, tagVariant, removeTag }">
                <b-input-group class="mb-2">
                  <div
                    class="d-inline-block"
                    style="font-size: 1.5rem;"
                  >
                    <div v-if="!isMobile">
                      <b-form-tag
                        v-for="tag in tags"
                        :key="tag"
                        :title="tag"
                        :variant="tagVariant"
                        class="mr-1 badge-primary bFormTag"
                        @remove="removeTag(tag)"
                      >
                        {{ tag }}
                      </b-form-tag>
                    </div>
                    <b-form-tag
                      v-for="tag in tags"
                      v-else
                      :key="tag"
                      :title="tag"
                      :variant="tagVariant"
                      class="mr-1 badge-primary bFormTagMobile"
                      @remove="removeTag(tag)"
                    >
                      {{ tag }}
                    </b-form-tag>
                  </div>
                </b-input-group>
              </template>
            </b-form-tags>
            <input
              id="files"
              type="file"
              multiple
              class="hidden"
              @change="storeFiles"
            >
            <label
              v-if="isMobile"
              for="files"
              :title="$i18n('mailbox.search')"
              class="btn btn-outline-primary btn-sm custom-label"
            >
              <i class="fas fa-paperclip" />
            </label>
            <label
              v-else
              for="files"
              :title="$i18n('mailbox.search')"
              class="btn btn-outline-primary btn-sm custom-label"
            >
              {{ $i18n('mailbox.search') }}
            </label>
          </div>
        </b-col>
      </b-row>

      <div class="p-2">
        <b-form-textarea
          id="textarea"
          v-model="mailBody"
          rows="12"
          max-rows="12"
        />
      </div>

      <b-row class="p-2">
        <b-col>
          <b-button
            size="sm"
            variant="outline-primary"
            :disabled="isBusy"
            @click="closeAndReturnToMailbox"
          >
            {{ $i18n('button.cancel') }}
          </b-button>
          <b-button
            size="sm"
            variant="primary"
            :disabled="isBusy || !(areAllEmailsValid && isSubjectValid)"
            @click="trySendEmail"
          >
            {{ $i18n('button.send') }}
          </b-button>
        </b-col>
      </b-row>
    </div>
  </container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import { sendEmail, setEmailProperties } from '@/api/mailbox'
import { uploadFile } from '@/api/uploads'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { store, MAILBOX_PAGE, MAIL_COMPOSITION_MODE } from '@/stores/mailbox'
import { MAX_UPLOAD_FILE_SIZE } from '@/consts'
import AddressBook from '@/components/Mailbox/AddressBook'
import FileUpload from '@/mixins/FileUpload'

export default {
  components: { Container, AddressBook },
  mixins: [FileUpload],
  props: {
    email: { type: Object, default: () => { } },
    mailboxes: { type: Array, default: () => { return [] } },
  },
  data () {
    return {
      isBusy: false,
      emailTo: [''],
      subject: '',
      mailBody: null,
      attachmentFilesName: [],
      attachmentFilesObjects: [],
      isMobile: false,
      currentEmailInput: '',
    }
  },
  computed: {
    showTitel () {
      switch (store.state.compositionMode) {
        case MAIL_COMPOSITION_MODE.ANSWER:
        case MAIL_COMPOSITION_MODE.ANSWER_ALL:
          return this.$i18n('mailbox.reply.full')
        case MAIL_COMPOSITION_MODE.FORWARD:
          return this.$i18n('mailbox.forward')
        default:
          return this.$i18n('mailbox.write')
      }
    },
    displayedMailDate () {
      return this.$dateFormatter.format(this.email.time, {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
      })
    },
    compositionMode () {
      return store.state.compositionMode
    },
    selectedMailbox () {
      if (store.state.selectedMailbox.length === 0) {
        const filteredMailbox = this.mailboxes.filter(mailbox => mailbox.id === this.email.mailboxId)
        return [filteredMailbox[0].id, filteredMailbox[0].name, this.email.mailboxFolder]
      }
      return store.state.selectedMailbox
    },
    areAllEmailsValid () {
      if (this.emailTo.length === 0) {
        return false
      }

      for (const email of this.emailTo) {
        if (!this.isValidEmail(email)) {
          return false
        }
      }

      return true
    },
    isSubjectValid () {
      return this.subject.length >= 3
    },
    // TODO: can be removed when forwarding of attachments is implemented
    showForwardAttachmentWarning () {
      return store.state.compositionMode === MAIL_COMPOSITION_MODE.FORWARD && this.email.attachments?.length > 0
    },
  },
  watch: {
    attachmentFilesName (newFiles, oldFiles) {
      const removedFiles = oldFiles.filter(file => !newFiles.includes(file))
      removedFiles.forEach(file => {
        const index = this.attachmentFilesObjects.findIndex(obj => obj.name === file)
        if (index !== -1) {
          this.attachmentFilesObjects.splice(index, 1)
        }
      })
    },
    compositionMode (newVal, oldVal) {
      if (newVal) {
        this.updateRecipientsForAnswerMode()
      } else if (!newVal && oldVal) {
        const index = this.emailTo.indexOf(this.email.from.address)
        if (index > -1) {
          this.emailTo.splice(index, 1)
        }
      }
    },
    email (newEmail, oldEmail) {
      if (newEmail && newEmail !== oldEmail) {
        this.subject = newEmail.subject
      }
    },
  },
  created () {
    window.addEventListener('resize', this.checkMobile)
    this.checkMobile()
    this.getMailBody()
    switch (this.compositionMode) {
      case MAIL_COMPOSITION_MODE.ANSWER:
      case MAIL_COMPOSITION_MODE.ANSWER_ALL:
        this.subject = 'Re: ' + this.email.subject
        this.updateRecipientsForAnswerMode()
        break
      case MAIL_COMPOSITION_MODE.FORWARD:
        this.subject = 'Fwd: ' + this.email.subject
        break
    }
  },
  destroyed () {
    window.removeEventListener('resize', this.checkMobile)
  },
  methods: {
    selectEmailFromAdressbock (mailAddress) {
      const index = this.emailTo.indexOf(mailAddress)

      if (index !== -1) {
        this.emailTo.splice(index, 1)
      } else {
        this.emailTo.push(mailAddress)
      }
    },
    getMailBody () {
      switch (this.compositionMode) {
        case MAIL_COMPOSITION_MODE.ANSWER:
        case MAIL_COMPOSITION_MODE.ANSWER_ALL:
        case MAIL_COMPOSITION_MODE.FORWARD: {
          const mailFromAddress = `<${this.email.from.address}>`
          const mailFromAndAddress = this.email.from.name ? `${this.email.from.name} ${mailFromAddress}` : mailFromAddress
          const mailFromAndDate = this.$i18n('mailbox.reply_header', {
            name: mailFromAndAddress,
            date: this.displayedMailDate,
          })
          let replacedContent = this.email.body.replace(/\r\n/g, '\n').replace(/\r/g, '\n')
          replacedContent = replacedContent.split('\n').map(line => '> ' + line).join('\n')

          this.mailBody = '\n\n ---\n\n' + mailFromAndDate + ': \n\n' + replacedContent
          break
        }
        default:
          this.mailBody = null
          break
      }
    },
    checkMobile () {
      this.isMobile = window.innerWidth <= 768
    },
    isValidEmail (email) {
      return /^((("[^"\\]+")|([a-zA-Z0-9_.+-]+))@[a-zA-Z0-9-]+\.[a-zA-Z]{2,})$/.test(email)
    },
    addEmailTag (tag) {
      let tagString = tag.trim()

      if (tagString.endsWith(';')) {
        tagString = tagString.slice(0, -1)
      }

      const splitRegex = /;\s*(?=(?:(?:[^"]*"){2})*[^"]*$)/

      const emailAddresses = tagString.split(splitRegex)
      const invalidEmails = []

      emailAddresses.forEach((email) => {
        email = email.trim()
        if (this.isValidEmail(email)) {
          this.emailTo.push(email)
        } else {
          console.log('Ungültige E-Mail-Adresse:', email)
          invalidEmails.push(email)
        }
      })
      this.currentEmailInput = invalidEmails.join('; ')
    },

    storeFiles (event) {
      // Stores files that were selected as attachments. Uploading is only done when the email is actually being sent.
      const files = Array.from(event.target.files)
      const filteredFiles = files.filter(file => file.size <= MAX_UPLOAD_FILE_SIZE)
      filteredFiles.forEach(file => {
        this.attachmentFilesName.push(file.name)
        this.attachmentFilesObjects.push(file)
      })

      // Show an error message if any of the selected files were too large
      if (files.length > filteredFiles.length) {
        pulseError(this.$i18n('mailbox.attachment.too_large_to_send'))
      }
    },
    async trySetEmailStatus (state) {
      showLoader()
      this.isBusy = true
      try {
        await setEmailProperties(this.email.id, state)
        this.setIsReadState(state)
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async trySendEmail () {
      showLoader()
      this.isBusy = true
      let attachments = []
      try {
        // load the attachment files into memory and upload them
        const loadFilePromises = this.attachmentFilesObjects.map(this.loadFile)
        const uploadPromises = loadFilePromises.map(promise => promise.then(file => {
          return uploadFile(file.name, file.content)
        }))
        const responses = await Promise.all(uploadPromises)
        attachments = responses.map(response => {
          return {
            uuid: response.uuid,
            filename: response.filename,
          }
        })

        const emailId = ([MAIL_COMPOSITION_MODE.ANSWER, MAIL_COMPOSITION_MODE.ANSWER_ALL].includes(this.compositionMode)) ? this.email.id : null
        await sendEmail(this.selectedMailbox[0], this.emailTo, null, null, this.subject, this.mailBody, attachments, emailId)
        this.closeAndReturnToMailbox()
        pulseSuccess(this.$i18n('mailbox.okay'))
      } catch (err) {
        const errorDescription = err.jsonContent ?? { message: '' }
        const errorMessage = `(${errorDescription.message ?? 'Unknown'})`
        pulseError(this.$i18n('mailbox.mailsend_unsuccess', { error: errorMessage }))
      }
      this.isBusy = false
      hideLoader()
    },
    toggleReadState () {
      this.trySetEmailStatus(!this.email.isRead)
    },
    setIsReadState (state) {
      return this.email.isRead
    },
    closeAndReturnToMailbox () {
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
    },
    updateRecipientsForAnswerMode () {
      this.emailTo.push(this.email.from.address)

      // if replying to all, add all except the currently selected mailbox to the recipients
      if (this.compositionMode === MAIL_COMPOSITION_MODE.ANSWER_ALL) {
        const addressToFilter = this.selectedMailbox[1] + '@'
        const additionalRecipients = this.email.to
          .map(x => x.address)
          .filter(x => !x.startsWith(addressToFilter))
        this.emailTo.push(...additionalRecipients)
      }
    },
    openAddressbook () {
      this.$refs.addressBook.show()
    },
  },
}
</script>

<style scoped>
.badge-primary {
  background-color: darkgrey;
}

.btn-outline-primary:hover {
  color: unset;
  background-color: unset;
}

.bFormTagMobile {
  font-size: 0.7rem;
}

.bFormTag {
  font-size: 0.9rem;
}

.flex-container {
  display: flex;
}
</style>
