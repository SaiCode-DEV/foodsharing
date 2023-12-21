<template>
  <Container
    :tag="showTitel"
    :title="showTitel"
    :toggle-visibility="true"
  >
    <b-modal
      id="modal_open_addressbook"
      ref="modal_open_addressbook"
      :title="$i18n('mailbox.global_addressbook')"
      hide-footer
      header-class="d-flex"
      content-class="pr-3 pt-3"
      size="xl"
      scrollable
    >
      <b-button-group class="mb-2">
        <b-button
          :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
          @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
        >
          {{ $i18n('terminology.groups') }}
        </b-button>
        <b-button
          :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
          @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
        >
          {{ $i18n('terminology.regions') }}
        </b-button>
      </b-button-group>
      <b-form-input
        v-model="filterName"
        :placeholder="$i18n('mailbox.search_name_email')"
        class="mb-2"
      />
      <b-list-group>
        <b-list-group-item
          v-for="filteredRegion in filteredRegions"
          :key="filteredRegion.id"
          class="pb-2"
          :class="{ 'selected-item': emailTo.includes(filteredRegion.emailAddress) }"
          href="#"
          @click="selectEmailFromAdressbock(filteredRegion.emailAddress)"
        >
          <b>{{ filteredRegion.name }}</b><br>
          {{ filteredRegion.emailAddress }}
        </b-list-group-item>
      </b-list-group>
    </b-modal>
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
            :tag-validator="isValidEmail"
            :limit="100"
            separator=" ,;"
            size="sm"
            class="mb-2"
          >
            <template #default="{ tags, inputAttrs, inputHandlers, tagVariant, addTag, removeTag }">
              <b-input-group class="mb-2">
                <b-form-input
                  v-bind="inputAttrs"
                  :placeholder="$i18n('mailbox.tag_recipient_hint')"
                  class="form-control"
                  v-on="inputHandlers"
                />
                <b-input-group-append>
                  <b-button
                    v-if="!isMobile"
                    variant="outline-primary"
                    @click="addTag()"
                  >
                    {{ $i18n('mailbox.add') }}
                  </b-button>
                  <b-button
                    v-else
                    variant="outline-primary"
                    @click="addTag()"
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

      <b-row>
        <b-col>
          <b-row class="p-2">
            <b-col md="2" />
            <b-col md="10">
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
import { sendEmail, setEmailProperties, listRegions } from '@/api/mailbox'
import { uploadFile } from '@/api/uploads'
import { hideLoader, pulseError, pulseSuccess, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { store, MAILBOX_PAGE, MAILBOX_ADDRESSBOOK_FILTER_TYPES } from '@/stores/mailbox'
import { MAX_UPLOAD_FILE_SIZE } from '@/consts'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'

export default {
  components: { Container },
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
      regions: [],
      filterName: null,
      filter: { type: MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS, name: null },
    }
  },
  computed: {
    MAILBOX_ADDRESSBOOK_FILTER_TYPES () {
      return MAILBOX_ADDRESSBOOK_FILTER_TYPES
    },
    filteredRegions () {
      const typeFilter = this.filter.type
      const nameFilter = this.filterName

      const filtered = this.regions.filter(region => {
        const typeMatch = region.type === typeFilter || typeFilter === 0
        const nameMatch = !nameFilter || region.name.toLowerCase().includes(nameFilter.toLowerCase())
        return typeMatch && nameMatch
      })

      return filtered
    },
    showTitel () {
      return store.state.answerMode ? this.$i18n('mailbox.reply.full') : this.$i18n('mailbox.write')
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
    answerMode () {
      return store.state.answerMode
    },
    answerAll () {
      return store.state.answerAll
    },
    selectedMailbox () {
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
    answerMode (newVal, oldVal) {
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
    if (this.answerMode) {
      this.subject = this.email.subject
      this.updateRecipientsForAnswerMode()
    }
  },
  destroyed () {
    window.removeEventListener('resize', this.checkMobile)
  },
  methods: {
    getButtonVariant (filterType) {
      return this.filter.type === filterType ? 'primary' : 'secondary'
    },
    selectEmailFromAdressbock (mailAddress) {
      const index = this.emailTo.indexOf(mailAddress)

      if (index !== -1) {
        this.emailTo.splice(index, 1)
      } else {
        this.emailTo.push(mailAddress)
      }
    },
    updateFilter (type) {
      this.filter.type = type
    },
    getMailBody () {
      if (this.answerMode) {
        const mailFromAddress = `<${this.email.from.address}>`
        const mailFromAndAddress = this.email.from.name ? `${this.email.from.name} ${mailFromAddress}` : mailFromAddress
        const mailFromAndDate = this.$i18n('mailbox.reply_header', { name: mailFromAndAddress, date: this.displayedMailDate })
        const replacedContent = `> ${this.email.body.replace(/\r/g, '\n')}`

        this.mailBody = mailFromAndDate + ': \n\n' + replacedContent
      } else {
        this.mailBody = null
      }
    },
    checkMobile () {
      this.isMobile = window.innerWidth <= 768
    },
    isValidEmail (email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
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

        const emailId = this.answerMode ? this.email.id : null
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
    /**
     * Returns a promise that loads a file into memory and encodes it as Base64.
     */
    loadFile (file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader()

        reader.onload = (event) => {
          const binaryStr = new Uint8Array(event.target.result)
          let base64 = ''
          binaryStr.forEach((byte) => {
            base64 += String.fromCharCode(byte)
          })
          base64 = window.btoa(base64)
          resolve({
            name: file.name,
            size: file.size,
            type: file.type,
            content: base64,
          })
        }

        reader.onerror = (error) => {
          reject(error)
        }

        reader.readAsArrayBuffer(file)
      })
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
      if (this.answerAll) {
        const addressToFilter = this.selectedMailbox[1] + '@'
        const additionalRecipients = this.email.to
          .map(x => x.address)
          .filter(x => !x.startsWith(addressToFilter))
        this.emailTo.push(...additionalRecipients)
      }
    },
    openAddressbook () {
      this.getRegions()
      this.$refs.modal_open_addressbook.show()
    },
    async getRegions () {
      const mailboxRegionsRateLimitInterval = 86400000 // 24 hours in Millisekunden
      const cacheRequestName = 'MailboxRegions'
      try {
        if (await getCacheInterval(cacheRequestName, mailboxRegionsRateLimitInterval)) {
          this.regions = await listRegions()

          await setCache(cacheRequestName, this.regions)
        } else {
          this.regions = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching regions:', e)
      }
    },
  },
}
</script>

<style scoped>
.selected-item {
  background-color: var(--fs-color-secondary-400);
}

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
