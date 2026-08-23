<template>
  <div v-if="email">
    <Container
      :tag="$t('mailbox.mail')"
      :title="$t('mailbox.mail')"
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
            {{ $t('mailbox.show_html_body') }}
          </b-form-checkbox>
          <div class="row">
            <div class="col col-auto">
              {{ $t('mailbox.from') }}:
            </div>
            <div class="col col-7 pl-0">
              <span>{{ fromHeaderName }}{{ fromHeaderAddress }}</span>
            </div>
            <div
              v-if="!viewIsMobile"
              class="col col-4 text-right"
            >
              {{ $t('mailbox.date') }}: {{ displayedMailDate }} Uhr
            </div>
          </div>
          <div class="row mt-1">
            <div class="col col-auto">
              {{ $t('mailbox.to') }}:
            </div>
            <div class="col col-8 col-md-10 pl-0">
              <span
                v-for="(mailAddress, index) in displayedEmails"
                :key="index"
              >
                {{ index > 0 ? (viewIsMobile ? ',\n' : ', ') : '' }}
                {{ mailAddress.length > 27 && viewIsMobile ? mailAddress.substring(0, 27) + "..." : mailAddress }}
              </span>
            </div>
            <div class="col col-1 text-right">
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
          <div v-if="viewIsMobile" class="row mt-1">
            <div class="col col-auto">
              {{ $t('mailbox.date') }}: {{ displayedMailDate }} Uhr
            </div>
          </div>
          <div class="row mt-1">
            <div class="col col-auto">
              <h5>{{ email.subject }}</h5>
              <!-- eslint-disable vue/no-v-html -->
              <!-- Sanitized in Modules/Mailbox/MailboxGateway.php getMessage() -->
              <div class="pt-2" v-html="emailBody" />
              <!-- eslint-enable -->
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
                    v-b-tooltip.hover="$t('mailbox.attachment.not_found_explanation')"
                  >
                    {{ attachment.fileName }} ({{ $t('mailbox.attachment.not_found') }})
                  </div>
                </b-list-group-item>
              </b-list-group>
            </div>
          </div>
        </div>
      </div>
      <MailboxFooterNav />
    </Container>
  </div>
</template>

<script>
import { sanitizeHtml } from '@/helper/sanitize-html'
import Container from '@/components/Container/Container.vue'
import MailboxFooterNav from './MailboxFooterNav.vue'
import MailboxMainNav from './MailboxMainNav.vue'
import { deleteEmail, setEmailProperties } from '@/api/mailbox'
import { hideLoader, pulseError, showLoader } from '@/script'
import i18n from '@/helper/i18n'
import { MAILBOX_PAGE, store } from '@/stores/mailbox'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import { formatFileSize } from '@/helper/number-formatting'

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
    fromHeaderName () {
      return this.email.from.name ? this.email.from.name : ''
    },
    fromHeaderAddress () {
      const name = this.email.from.name ? this.email.from.name : ''
      const address = this.email.from.address
      const result = name ? ` <${address}>` : address
      return result || `(${this.$t('mailbox.unknown_sender')})`
    },
    hasHtmlBody () {
      return this.email.bodyHtml && this.email.bodyHtml.length >= 0
    },
    emailBody () {
      if (this.hasHtmlBody && this.showHtmlBody) {
        return sanitizeHtml(this.email.bodyHtml)
      } else {
        return this.addLineBreaks(this.addLinks(this.escapeHtml(this.email.body)))
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
      return formatFileSize(bytes)
    },
    // The body is plain text and goes into v-html, so everything that looks like
    // markup has to be escaped before the links and line breaks are added.
    escapeHtml (text) {
      if (!text) return ''
      const el = document.createElement('div')
      el.textContent = text
      return el.innerHTML
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
