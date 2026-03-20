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
          {{ $t('mailbox.sender') }}
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
          {{ $t('mailbox.recipient') }}
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
                  :placeholder="$t('mailbox.tag_recipient_hint')"
                  class="form-control"
                  v-on="inputHandlers"
                  @keydown.enter.prevent="addEmailTag(inputAttrs.value)"
                  @blur="addEmailTag(inputAttrs.value)"
                />
                <b-input-group-append>
                  <b-button
                    v-if="!isMobile"
                    variant="outline-primary"
                    @click="addEmailTag(inputAttrs.value)"
                  >
                    {{ $t('mailbox.add') }}
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
                    {{ $t('mailbox.addressbook') }}
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
          {{ $t('mailbox.subject') }}
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
          {{ $t('mailbox.attachment.attach') }}
        </b-col>
        <b-col md="10">
          <b-alert
            v-if="showForwardAttachmentWarning"
            variant="danger"
            show
          >
            {{ $t('mailbox.forward_attachment_warning') }}
          </b-alert>
          <div class="flex-container">
            <FileInput
              :value="attachmentFilesObjects"
              :disabled="isBusy || !mayAttachMoreFiles"
              :max-files="MAX_NUMBER_OF_EMAIL_ATTACHMENTS"
              @update:value="storeFiles"
            />
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
            {{ $t('button.cancel') }}
          </b-button>
          <b-button
            size="sm"
            variant="primary"
            :disabled="isBusy || !(areAllEmailsValid && isSubjectValid)"
            @click="trySendEmail"
          >
            {{ $t('button.send') }}
          </b-button>
        </b-col>
      </b-row>
    </div>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import FileInput from '@/components/UI/FileInput.vue'
import { sendEmail, setEmailProperties } from '@/api/mailbox'
import { uploadFile } from '@/api/uploads'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { store, MAILBOX_PAGE, MAIL_COMPOSITION_MODE, MAX_NUMBER_OF_EMAIL_ATTACHMENTS } from '@/stores/mailbox'
import AddressBook from '@/components/Mailbox/AddressBook'
import FileUpload from '@/mixins/FileUpload'

export default {
  components: { Container, AddressBook, FileInput },
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
      attachmentFilesObjects: [],
      isMobile: false,
      currentEmailInput: '',
      MAX_NUMBER_OF_EMAIL_ATTACHMENTS,
    }
  },
  computed: {
    showTitel () {
      switch (store.state.compositionMode) {
        case MAIL_COMPOSITION_MODE.ANSWER:
        case MAIL_COMPOSITION_MODE.ANSWER_ALL:
          return this.$t('mailbox.reply.full')
        case MAIL_COMPOSITION_MODE.FORWARD:
          return this.$t('mailbox.forward')
        default:
          return this.$t('mailbox.write')
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
    mayAttachMoreFiles () {
      return this.attachmentFilesObjects.length < MAX_NUMBER_OF_EMAIL_ATTACHMENTS
    },
  },
  watch: {
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
          const mailFromAndDate = this.$t('mailbox.reply_header', {
            name: mailFromAndAddress,
            date: this.displayedMailDate,
          })
          // decode HTML entities in the mail body (e.g. &gt; to >)
          // The backend re-encodes the mail body to prevent XSS
          const decoder = document.createElement('textarea')
          decoder.innerHTML = this.email.body
          const decodedContent = decoder.value

          let replacedContent = decodedContent.replace(/\r\n/g, '\n').replace(/\r/g, '\n')
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
      return /^((("[^"\\]+")|([a-zA-Z0-9_.+-]+))@([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,})$/.test(email)
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
          invalidEmails.push(email)
        }
      })
      this.currentEmailInput = invalidEmails.join('; ')
    },

    storeFiles (files) {
      this.attachmentFilesObjects = files
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
        pulseSuccess(this.$t('mailbox.okay'))
      } catch (err) {
        const errorDescription = err.jsonContent ?? { message: '' }
        const errorMessage = `(${errorDescription.message ?? 'Unknown'})`
        pulseError(this.$t('mailbox.mailsend_unsuccess', { error: errorMessage }))
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
