<template>
  <div>
    <div class="row p-3">
      <div class="col col-12 col-sm-3">
        <Container
          :tag="$i18n('mailbox.title')"
          :title="$i18n('mailbox.title')"
          :toggle-visibility="mailboxes.length > defaultAmount"
          @show-full-list="showFullList"
          @reduce-list="reduceList"
        >
          <div class="card bg-white">
            <MailboxFolder
              v-for="mailbox in sortedMailboxes"
              :key="mailbox.id"
              :mailbox-id="mailbox.id"
              :mailbox-name="mailbox.name"
              :full-name="getFullMailboxName(mailbox.name)"
              :unread-count="mailbox.count"
              :selected-mailbox-id.sync="selectedMailboxId"
              :folder-id.sync="folderId"
              :selected-mailbox-name.sync="selectedMailboxName"
            />
          </div>
        </Container>
      </div>
      <div class="col-12 col-sm-9">
        <MailboxView
          v-if="page === MAILBOX_PAGE.EMAIL_LIST"
          :selected-email-id.sync="selectedEmailId"
        />
        <MailboxSingleEmailView
          v-if="page === MAILBOX_PAGE.READ_EMAIL"
          :email="email"
        />
        <MailboxNewAndAnswer
          v-if="page === MAILBOX_PAGE.NEW_EMAIL"
          :email="email"
          :mailboxes="sortedMailboxes"
        />
      </div>
    </div>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import ListToggleMixin from '@/mixins/ContainerToggleMixin'
import MailboxFolder from './MailboxFolder'
import MailboxView from './MailboxView.vue'
import MailboxNewAndAnswer from './MailboxNewAndAnswer.vue'
import MailboxSingleEmailView from './MailboxSingleEmailView.vue'
import { hideLoader, pulseError, showLoader } from '@/script'
import { getEmail } from '@/api/mailbox'
import i18n from '@/helper/i18n'
import { store, MAILBOX_PAGE, MAILBOX_FOLDER } from '@/stores/mailbox'

export default {
  components: { Container, MailboxFolder, MailboxView, MailboxNewAndAnswer, MailboxSingleEmailView },
  mixins: [ListToggleMixin],
  props: {
    hostname: { type: String, required: true },
    mailboxes: { type: Array, default: () => { return [] } },
    emailId: { type: Number, default: null },
    mailboxId: { type: Number, default: null },
  },
  data () {
    return {
      selectedMailboxId: this.mailboxId,
      folderId: null,
      selectedMailboxName: null,
      selectedEmailId: this.emailId,
      email: null,
    }
  },
  computed: {
    mailboxTitle () {
      return this.selectedMailboxName ? this.selectedMailboxName : 'keine Mailbox ausgewählt'
    },
    page () {
      return store.state.page
    },
    sortedMailboxes () {
      return [...this.mailboxes].sort((a, b) => a.name.localeCompare(b.name))
    },
  },
  watch: {
    async selectedEmailId () {
      // fetch an email whenever the emailId is updated
      await this.loadSelectedEmail()
    },
  },
  created () {
    this.MAILBOX_PAGE = MAILBOX_PAGE
    if (this.selectedEmailId) {
      // If an email was specified (e.g. by clicking a link on the dashboard), that email is shown.
      this.loadSelectedEmail()
      store.setPage(MAILBOX_PAGE.READ_EMAIL)
    } else if (this.mailboxes.length > 0) {
      // If a mailbox was specified, that mailbox is selected. Else the first mailbox will be selected.
      const foundSelectedMailbox = this.selectedMailboxId ? this.mailboxes.find(m => m.id === this.selectedMailboxId) : null
      if (foundSelectedMailbox) {
        store.setMailbox(foundSelectedMailbox.id, foundSelectedMailbox.name, MAILBOX_FOLDER.INBOX)
        store.setPage(MAILBOX_PAGE.EMAIL_LIST)
      } else {
        store.setMailbox(this.sortedMailboxes[0].id, this.sortedMailboxes[0].name, MAILBOX_FOLDER.INBOX)
        store.setPage(MAILBOX_PAGE.EMAIL_LIST)
      }
    }
  },
  methods: {
    getFullMailboxName (mailboxName) {
      return mailboxName + '@' + this.hostname
    },
    async loadSelectedEmail () {
      // Fetches the selected email from the server, if any is selected
      if (this.selectedEmailId) {
        showLoader()
        this.isBusy = true
        try {
          this.email = await getEmail(this.selectedEmailId)
        } catch (e) {
          pulseError(i18n('error_unexpected'))
        }
        this.isBusy = false
        hideLoader()
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.card-header .row {
  margin-top: -6px;
  margin-bottom: -6px;
  font-weight: bold;
}

::v-deep a {
  font-weight: normal !important;
}

::v-deep .unReadMail {
  font-weight: 600 !important;
}
</style>
