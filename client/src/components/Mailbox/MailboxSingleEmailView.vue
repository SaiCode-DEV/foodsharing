<template>
  <div v-if="email">
    <Container
      :tag="$i18n('mailbox.mail')"
      :title="$i18n('mailbox.mail')"
    >
      <div class="card bg-white">
        <MailboxMainNav
          :selected-email="email"
          :folder-type="email.mailboxFolder"
          @toggle-email-state="toggleEmailState"
          @try-move-email="tryMoveEmail"
          @try-delete-email="tryDeleteEmail"
        />
        <div class="border-left border-right p-2">
          <div class="row">
            <div class="col col-auto">
              {{ $i18n('mailbox.from') }}:
            </div>
            <div
              class="col col-6"
              v-html="fromHeader"
            />
            <div class="col col-5 text-right">
              {{ $i18n('mailbox.date') }} : {{ displayedMailDate }} Uhr
            </div>
          </div>
          <div class="row mt-1">
            <div class="col col-1 d-flex align-items-center">
              {{ $i18n('mailbox.to') }}:
            </div>
            <div class="toClass">
              <span
                v-for="(mailAddress, index) in displayedEmails"
                :key="index"
              >
                {{ index > 0 ? ', ' : '' }}{{ mailAddress }}
              </span>
              <b-button
                v-if="shouldShowToggleButton"
                size="sm"
                variant="outline-primary"
                @click="toggleEmails"
              >
                {{ isExpanded ? "... " + $i18n('mailbox.less') : "... " + $i18n('mailbox.more') }}
              </b-button>
            </div>
          </div>
          <div class="pt-2">
            <h5>{{ email.subject }}</h5>
            <div
              class="pt-2"
              v-html="getBody(email)"
            />
            <b-list-group
              v-if="email.attachments"
              horizontal
              class="pt-2"
            >
              <b-list-group-item
                v-for="(attachment, index) in email.attachments"
                :key="attachment.id"
              >
                <b-link
                  v-if="attachment.size > 0"
                  :download="attachment.fileName"
                  :href="attachmentDownloadLink(attachment.hashedFileName, email.id, index)"
                >
                  {{ attachment.fileName }} ({{ formatFileSize(attachment.size) }})
                </b-link>
                <div
                  v-else
                  v-b-tooltip.hover="$i18n('mailbox.attachment.not_found_explanation')"
                >
                  {{ attachment.fileName }} ({{ $i18n('mailbox.attachment.not_found') }})
                </div>
              </b-list-group-item>
            </b-list-group>
          </div>
        </div>
        <MailboxFooterNav />
      </div>
    </container>
  </div>
</template>

<script>
import DOMPurify from 'dompurify'
import Container from '@/components/Container/Container.vue'
import MailboxFooterNav from './MailboxFooterNav.vue'
import MailboxMainNav from './MailboxMainNav.vue'
import { deleteEmail, setEmailProperties } from '@/api/mailbox'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { store, MAILBOX_PAGE } from '@/stores/mailbox'

export default {
  components: { Container, MailboxMainNav, MailboxFooterNav },
  props: {
    email: { type: Object, default: null },
  },
  data () {
    return {
      isBusy: false,
      isRead: null,
      isExpanded: false,
    }
  },
  computed: {
    allEmailAddresses () {
      return this.email.to.map(recipient => recipient.address)
    },
    shouldShowToggleButton () {
      return this.allEmailAddresses.length > 2
    },
    displayedEmails () {
      if (this.isExpanded) {
        return this.allEmailAddresses
      } else {
        return this.allEmailAddresses.slice(0, 2)
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
    fromHeader () {
      const name = this.email.from.name ? this.email.from.name : ''
      const address = this.email.from.address
      const combined = name ? `<a href="mailto:${address}">${address}</a>` : address
      const result = name ? combined : address
      return result || `(${this.$i18n('mailbox.unknown_sender')})`
    },
  },
  mounted () {
    if (this.email !== null && this.email.isRead !== true) {
      this.isRead = this.email.isRead
      this.trySetEmailStatus()
    }
  },
  methods: {
    toggleEmails () {
      this.isExpanded = !this.isExpanded
    },
    async tryMoveEmail (folder) {
      showLoader()
      this.isBusy = true
      try {
        await setEmailProperties(this.email.id, null, folder)
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    toggleEmailState () {
      this.trySetEmailStatus()
      this.closeAndReturnToMailbox()
    },
    async trySetEmailStatus () {
      const state = !this.isRead
      showLoader()
      this.isBusy = true
      try {
        await setEmailProperties(this.email.id, state, null)
        this.isRead = state
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async tryDeleteEmail () {
      showLoader()
      this.isBusy = true
      try {
        await deleteEmail(this.email.id)
        this.closeAndReturnToMailbox()
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    closeAndReturnToMailbox () {
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
    },
    formatFileSize (bytes) {
      const units = ['B', 'kB', 'MB']
      let u = 0
      while (Math.round(Math.abs(bytes) * 10) / 10 >= 1024 && u < units.length - 1) {
        bytes /= 1024
        u++
      }

      return bytes.toFixed(1) + ' ' + units[u]
    },
    getBody (email) {
      return this.getPlainBody(DOMPurify.sanitize(email.bodyHtml ?? email.body))
    },
    getPlainBody (text) {
      return this.addLineBreaks(this.addLinks((text)))
    },
    addLineBreaks (text) {
      return text ? text.replace(/\\n|\n/g, '<br>') : ''
    },
    addLinks (text) {
      const urlRegex = /(https?:\/\/[^\s]+)/g
      return text ? text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>') : ''
    },
    attachmentDownloadLink (hashedFileName, emailId, attachmentIndex) {
      return hashedFileName.startsWith('old:')
        ? this.$url('mailboxOldAttachment', emailId, attachmentIndex)
        : this.$url('upload', hashedFileName)
    },
  },
}
</script>

<style scoped>
.toClass {
  max-width: 40rem;
  padding-top: 0.5rem;
}
</style>
