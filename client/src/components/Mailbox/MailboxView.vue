<template>
  <div v-if="selectedMailbox">
    <Container
      :tag="$t('mailbox.mails')"
      :title="$t('mailbox.mails')"
      :toggle-visibility="selected"
    >
      <div class="card bg-white">
        <MailboxMainNav
          :selected-email="selected"
          :folder-type="selectedMailbox[2]"
          @try-delete-email="tryDeleteEmail"
          @try-move-email="tryMoveEmail"
          @select-all-rows="selectAllRows"
          @toggle-read-state-for-mails="toggleReadStateForMails"
          @clear-selected="clearSelected"
        />
        <span v-if="isBusy && page === 0" class="d-block mx-auto">
          <i class="fas fa-spinner fa-spin mx-auto" />
        </span>
        <div v-else-if="selectedMailbox[2] != null && mailboxMails.length > 0">
          <b-table
            ref="selectableTable"
            :fields="columns"
            :items="mailboxMails"
            select-mode="multi"
            responsive="sm"
            selectable
            small
            stacked="sm"
            hover
            @row-selected="onRowSelected"
          >
            <template #cell(selected)="{ rowSelected }">
              <template v-if="rowSelected">
                <span aria-hidden="true">&check;</span>
                <span class="sr-only">Selected</span>
              </template>
              <template v-else>
                <span aria-hidden="true">&nbsp;</span>
                <span class="sr-only">Not selected</span>
              </template>
            </template>
            <template #cell(sender)="row">
              <a
                :key="row.item.id"
                href="#"
                :class="{ unReadMail: !row.item.isRead }"
                @click="showEmail(row.item.id)"
              >
                {{ formatEmailAddress(row.item.from) }}
              </a>
            </template>
            <template #cell(recipient)="row">
              <a
                :key="row.item.id"
                href="#"
                :class="{ unReadMail: !row.item.isRead }"
                @click="showEmail(row.item.id)"
              >
                {{ formatRecipientAddresses(row.item.to) }}
              </a>
            </template>
            <template #cell(subject)="row">
              <a
                :key="row.item.id"
                href="#"
                :class="{ unReadMail: !row.item.isRead }"
                @click="showEmail(row.item.id)"
              >
                {{ row.item.subject }}
              </a>
            </template>
            <template #cell(date)="row">
              {{ $dateFormatter.dateBasic(row.item.time) }}
            </template>
            <template #cell(attachments)="row">
              <i
                v-if="row.item.attachments && row.item.attachments.length > 0"
                class="fas fa-paperclip"
              />
            </template>
          </b-table>
          <div class="text-center mb-2">
            <small v-if="noMorePages">
              {{ $t('pickup.overview.allLoaded') }}
            </small>
            <b-button
              v-else
              size="sm"
              :disabled="isBusy"
              class="d-block mx-auto"
              @click="loadNextPage"
            >
              <i v-if="isBusy" class="fas fa-spinner fa-spin" />
              <span v-else>{{ $t('pickup.overview.menu.loadMore') }}</span>
            </b-button>
          </div>
        </div>
        <div v-else class="m-3 text-center">
          {{ $t('mailbox.empty') }}
        </div>
        <div v-if="emailId" class="border p-2" />
      </div>
    </Container>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import MailboxMainNav from './MailboxMainNav.vue'
import { BTable } from 'bootstrap-vue'
import { hideLoader, pulseError, showLoader } from '@/script'
import { deleteEmail, getAllEmails, setEmailProperties } from '@/api/mailbox'
import i18n from '@/helper/i18n'
import { store, MAILBOX_FOLDER, MAILBOX_PAGE } from '@/stores/mailbox'

const PAGE_SIZE = 50

export default {
  components: { Container, BTable, MailboxMainNav },
  data () {
    return {
      emailId: null,
      mailboxMails: [],
      selected: [],
      page: 0,
      noMorePages: false,
      isBusy: false,
    }
  },
  computed: {
    selectedMailbox () {
      return store.state.selectedMailbox
    },
    columns () {
      const baseColumns = [
        { key: 'selected', label: '', sortable: false, class: 'align-middle leftcolumn' },
        { key: 'subject', sortable: false, label: this.$t('mailbox.subject'), class: 'align-middle' },
        { key: 'date', label: this.$t('mailbox.date'), sortable: false, class: 'align-middle' },
        { key: 'attachments', label: '', sortable: true, class: 'align-middle' },
      ]

      const useSender = this.selectedMailbox[2] === MAILBOX_FOLDER.INBOX
      const senderOrRecipientColumn = {
        key: useSender ? 'sender' : 'recipient',
        label: useSender ? this.$t('mailbox.from') : this.$t('mailbox.to'),
        sortable: false,
        class: 'align-middle',
      }
      return [baseColumns[0], senderOrRecipientColumn, ...baseColumns.slice(1)]
    },
  },
  watch: {
    selectedMailbox () {
      if (this.$refs.selectableTable) {
        this.$refs.selectableTable.isBusy = true
      }
      this.page = 0
      this.noMorePages = false
      this.mailboxMails = []
      this.selected = []
      this.loadNextPage()
      if (this.$refs.selectableTable) {
        this.$refs.selectableTable.isBusy = false
      }
    },
  },
  created () {
    this.loadNextPage()
  },
  methods: {
    /**
     * Removes one or more emails from the client-side list.
     */
    removeEmailsFromList (emails) {
      for (let i = 0; i < emails.length; i++) {
        const index = this.mailboxMails.indexOf(emails[i])
        if (index >= 0) {
          this.mailboxMails.splice(index, 1)
        }
      }
    },
    async tryDeleteEmail () {
      showLoader()
      this.isBusy = true
      try {
        await Promise.all(this.selected.map(email => deleteEmail(email.id)))
        this.removeEmailsFromList(this.selected)
        this.selected = []
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async tryMoveEmail (folder) {
      showLoader()
      this.isBusy = true
      try {
        await Promise.all(this.selected.map(email => setEmailProperties(email.id, null, folder)))
        this.removeEmailsFromList(this.selected)
        this.selected = []
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    async loadNextPage () {
      this.isBusy = true
      try {
        const emails = await getAllEmails(this.selectedMailbox[0], this.selectedMailbox[2], this.mailboxMails.length, PAGE_SIZE)
        if (emails.length > 0) {
          this.mailboxMails.push(...emails)
          this.page++
        } else {
          this.noMorePages = true
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
    },
    formatEmailAddress (address) {
      const result = (address.name !== undefined && address.name !== null) ? address.name : address.address
      return result ?? `(${this.$t('mailbox.unknown_sender')})`
    },
    formatRecipientAddresses (addresses) {
      return addresses.map(address => this.formatEmailAddress(address)).join(', ')
    },
    showEmail (emailId) {
      this.emailId = emailId
      store.setPage(MAILBOX_PAGE.READ_EMAIL)
      this.$emit('update:selected-email-id', this.emailId)
    },
    async toggleReadStateForMails () {
      showLoader()
      this.isBusy = true
      const areAnyUnread = this.selected.some((item) => !item.isRead)

      this.selected.forEach((item) => {
        item.isRead = areAnyUnread
      })

      try {
        await Promise.all(this.selected.map(email => setEmailProperties(email.id, areAnyUnread, this.selectedMailbox[2])))
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      this.isBusy = false
      hideLoader()
    },
    onRowSelected (items) {
      this.selected = items
    },
    selectAllRows () {
      this.$refs.selectableTable.selectAllRows()
    },
    clearSelected () {
      this.$refs.selectableTable.clearSelected()
    },
  },
}
</script>

<style lang="scss">
@media (max-width: 576px) {
  .table th, .table td {
    border-top: none;
  }
  .table tr {
    border-top: 1px solid var(--fs-border-default);
    margin: 0.5rem 0;
  }
  .table tr:first-child {
    border-top: none;
  }
  .table tr > td:is(:first-child, :last-child) {
    display: none !important;;
  }
  .table.b-table.b-table-stacked-sm > tbody > tr > [data-label]::before {
    content: none;
  }

   .table-sm th, .table-sm td {
     padding: 0.4rem;
     min-width: 24rem;
   }
}
</style>
