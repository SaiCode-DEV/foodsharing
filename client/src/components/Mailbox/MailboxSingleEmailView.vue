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
          <b-form-checkbox
            v-if="hasHtmlBody"
            v-model="showHtmlBody"
            class="text-right"
            switch
          >
            {{ $i18n('mailbox.show_html_body') }}
          </b-form-checkbox>
          <div class="row">
            <div class="col col-auto">
              {{ $i18n('mailbox.from') }}:
            </div>
            <div class="col col-7 pl-0">
              <span v-html="fromHeader" />
            </div>
            <div
              v-if="!viewIsMobile"
              class="col col-4 text-right"
            >
              {{ $i18n('mailbox.date') }} : {{ displayedMailDate }} Uhr
            </div>
          </div>
          <div class="row mt-1">
            <div class="col col-auto">
              {{ $i18n('mailbox.to') }}:
            </div>
            <div class="col col-8 col-md-11 pl-0">
              <span
                v-for="(mailAddress, index) in displayedEmails"
                :key="index"
                :class="{ 'text-truncate': !viewIsMobile }"
              >
                {{ index > 0 ? (viewIsMobile ? ',\n' : ', ') : '' }}{{ mailAddress }}
              </span>
            </div>
            <div class="col col-1">
              <b-button
                v-if="shouldShowToggleButton"
                size="sm"
                variant="outline-primary"
                @click="toggleEmails"
              >
                <i :class="{'fas fa-caret-up': isExpanded, 'fas fa-caret-down': !isExpanded}" />
              </b-button>
            </div>
          </div>
          <div class="row mt-1">
            <div class="col col-auto">
              <h5>{{ email.subject }}</h5>
              <div class="pt-2" v-html="emailBody" />
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
        </div>
      </div>
      <MailboxFooterNav />
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
import { MAILBOX_PAGE, store } from '@/stores/mailbox'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'

export default {
  components: { Container, MailboxMainNav, MailboxFooterNav },
  mixins: [MediaQueryMixin],
  props: {
    email: { type: Object, default: null },
  },
  data () {
    return {
      isBusy: false,
      isExpanded: false,
      showHtmlBody: false,
    }
  },
  computed: {
    allEmailAddresses () {
      return this.email.to.map(recipient => recipient.address)
    },
    emailAddressCount () {
      return this.viewIsMobile ? 1 : 3
    },
    shouldShowToggleButton () {
      return this.allEmailAddresses.length > this.emailAddressCount
    },
    displayedEmails () {
      if (this.isExpanded) {
        return this.allEmailAddresses
      } else {
        return this.allEmailAddresses.slice(0, this.emailAddressCount)
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
    hasHtmlBody () {
      return this.email.bodyHtml && this.email.bodyHtml.length >= 0
    },
    emailBody () {
      if (this.hasHtmlBody && this.showHtmlBody) {
        return DOMPurify.sanitize(this.email.bodyHtml, {
          USE_PROFILES: { html: true },
        })
      } else {
        return this.addLineBreaks(this.addLinks(this.email.body))
      }
    },
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
      const state = !this.email.isRead
      showLoader()
      this.isBusy = true
      try {
        await setEmailProperties(this.email.id, state, null)
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
    addLineBreaks (text) {
      return text ? text.replace(/\\n|\n/g, '<br>') : ''
    },
    addLinks (text) {
      const lines = text ? text.split('\n') : []

      // Remove '[' and ']' for each line for Markdown markup
      const step1 = lines.map(line => line.replace(/^\[/g, '').trim())
      const step2 = step1.map(line => line.replace(/]$/g, '').trim())

      const urlRegex = /(https?:\/\/\S+)/g
      return step2.map(line =>
        line.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'),
      ).join('\n')
    },
    attachmentDownloadLink (hashedFileName, emailId, attachmentIndex) {
      return hashedFileName.startsWith('old:')
        ? this.$url('mailboxOldAttachment', emailId, attachmentIndex)
        : this.$url('upload', hashedFileName)
    },
  },
}
</script>
