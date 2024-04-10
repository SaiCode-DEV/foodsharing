<template>
  <div>
    <div class="border">
      <b-row class="p-2">
        <b-col cols="8">
          <b-button
            v-if="page === MAILBOX_PAGE.READ_EMAIL || page === MAILBOX_PAGE.EMAIL_LIST"
            v-b-tooltip.hover
            :title="$i18n('mailbox.delete')"
            size="sm"
            variant="outline-primary"
            :disabled="areMailsNotSelected && page === MAILBOX_PAGE.EMAIL_LIST"
            @click="showModalToDeleteEmail"
          >
            <i class="fas fa-trash-alt" />
          </b-button>
          <b-dropdown
            v-if="page === MAILBOX_PAGE.READ_EMAIL"
            id="dropdown-reply"
            :text="$i18n('mailbox.reply.short')"
            class="m-md-2"
            size="sm"
            variant="outline-primary"
            split
            :disabled="!isValidSender"
            @click="showMailPage(MAIL_COMPOSITION_MODE.ANSWER)"
          >
            <b-dropdown-item
              @click="showMailPage(MAIL_COMPOSITION_MODE.ANSWER)"
            >
              {{ $i18n('mailbox.reply.short') }}
            </b-dropdown-item>
            <b-dropdown-item
              @click="showMailPage(MAIL_COMPOSITION_MODE.ANSWER_ALL)"
            >
              {{ $i18n('mailbox.reply_all') }}
            </b-dropdown-item>
          </b-dropdown>
          <b-button
            v-if="page === MAILBOX_PAGE.READ_EMAIL"
            v-b-tooltip.hover
            class="mr-md-2"
            size="sm"
            variant="outline-primary"
            @click="showMailPage(MAIL_COMPOSITION_MODE.FORWARD)"
          >
            <i class="fas fa-share" /> {{ $i18n('mailbox.forward') }}
          </b-button>
          <b-button
            v-if="page === MAILBOX_PAGE.EMAIL_LIST"
            v-b-tooltip.hover
            :title="getTranslationForReadOrUnReadState"
            size="sm"
            variant="outline-primary"
            :disabled="areMailsNotSelected"
            @click="mailboxViewToggleReadStateForMails"
          >
            <i :class="readOrUnreadIconClass" />
          </b-button>
          <b-button
            v-if="page === MAILBOX_PAGE.READ_EMAIL"
            v-b-tooltip.hover
            :title="getTranslationForReadOrUnReadState"
            size="sm"
            variant="outline-primary"
            @click="mailboxSingleEmailViewToggleEmailState"
          >
            <i :class="readOrUnreadIconClass" />
          </b-button>
          <b-button
            v-if="page === MAILBOX_PAGE.EMAIL_LIST && !isSelected"
            size="sm"
            variant="outline-primary"
            @click="mailboxViewSelectAllRows"
          >
            {{ $i18n('mailbox.mark_all') }}
          </b-button>
          <b-button
            v-else-if="page === MAILBOX_PAGE.EMAIL_LIST"
            size="sm"
            variant="outline-primary"
            @click="mailboxViewClearSelected"
          >
            {{ $i18n('mailbox.mark_none') }}
          </b-button>
          <b-dropdown
            v-if="page === MAILBOX_PAGE.READ_EMAIL || page === MAILBOX_PAGE.EMAIL_LIST"
            id="dropdown-move-to"
            :text="$i18n('mailbox.move_to')"
            class="m-md-2 pt-2 pt-md-0"
            size="sm"
            variant="outline-primary"
            :disabled="areMailsNotSelected && page === MAILBOX_PAGE.EMAIL_LIST"
          >
            <b-dropdown-item
              @click="moveEmail"
            >
              {{ getMovedToFolderTranslation() }}
            </b-dropdown-item>
          </b-dropdown>
        </b-col>
        <b-col
          cols="4"
          class="text-right"
        >
          <b-button
            size="sm"
            variant="primary"
            @click="showMailPage(MAIL_COMPOSITION_MODE.NEW)"
          >
            {{ $i18n('mailbox.write') }}
          </b-button>
        </b-col>
      </b-row>
    </div>
    <b-modal
      v-model="showEmailDeletionConfirmationModal"
      :title="$i18n('mailbox.conformation_modal.title')"
      :cancel-title="$i18n('globals.close')"
      @ok="mailboxEmailSingleViewDeleteEmail"
      @cancel="cancelEmailDeletion"
    >
      {{ $i18n('mailbox.conformation_modal.message') }}
    </b-modal>
  </div>
</template>

<script>
import { store, MAILBOX_PAGE, MAILBOX_FOLDER, MAIL_COMPOSITION_MODE } from '@/stores/mailbox'

export default {
  props: {
    folderType: { type: Number, required: true },
    selectedEmail: { type: [Array, Object], required: true },
  },
  data () {
    return {
      isSelected: false,
      isRead: false,
      showEmailDeletionConfirmationModal: false,
    }
  },
  computed: {
    page () {
      return store.state.page
    },
    areMailsNotSelected () {
      return this.selectedEmail < 1
    },
    getTranslationForReadOrUnReadState () {
      return this.isMarkedAsReadState ? this.$i18n('mailbox.mark_as_read') : this.$i18n('mailbox.mark_as_unread')
    },
    readOrUnreadIconClass () {
      return this.isMarkedAsReadState ? 'fas fa-eye' : 'fas fa-eye-slash'
    },
    isMarkedAsReadState () {
      if (Array.isArray(this.selectedEmail)) {
        return this.selectedEmail.some((item) => !item.isRead)
      } else if (typeof this.selectedEmail === 'object') {
        // When looking at an email, it is always marked as read and can only be marked as unread
        return false
      } else {
        throw new Error('Unexpected type of selectedEmail')
        return null
      }
    },
    isValidSender () {
      return this.selectedEmail.from.address?.length > 0
    },
  },
  created () {
    this.MAILBOX_PAGE = MAILBOX_PAGE
    this.MAIL_COMPOSITION_MODE = MAIL_COMPOSITION_MODE
  },
  methods: {
    getMovedToFolderTranslation () {
      const translations = {
        [MAILBOX_FOLDER.INBOX]: this.$i18n('mailbox.trash'),
        [MAILBOX_FOLDER.SENT]: this.$i18n('mailbox.trash'),
        [MAILBOX_FOLDER.TRASH]: this.$i18n('mailbox.inbox'),
      }
      return translations[this.folderType]
    },
    showMailPage (compositionMode) {
      store.setCompositionMode(compositionMode)
      store.setPage(MAILBOX_PAGE.NEW_EMAIL)
    },
    mailboxViewSelectAllRows () {
      this.$emit('select-all-rows')
      this.isSelected = true
    },
    mailboxSingleEmailViewToggleEmailState () {
      this.$emit('toggle-email-state')
    },
    mailboxViewToggleReadStateForMails () {
      this.$emit('toggle-read-state-for-mails')
    },
    mailboxViewClearSelected () {
      this.$emit('clear-selected')
      this.isSelected = false
    },
    showModalToDeleteEmail () {
      this.showEmailDeletionConfirmationModal = true
    },
    mailboxEmailSingleViewDeleteEmail () {
      this.$emit('try-delete-email')
      this.showEmailDeletionConfirmationModal = false
    },
    cancelEmailDeletion () {
      this.showEmailDeletionConfirmationModal = false
    },
    moveEmail () {
      const folderMappings = {
        [MAILBOX_FOLDER.INBOX]: MAILBOX_FOLDER.TRASH,
        [MAILBOX_FOLDER.SENT]: MAILBOX_FOLDER.TRASH,
        [MAILBOX_FOLDER.TRASH]: MAILBOX_FOLDER.INBOX,
      }
      const newFolder = folderMappings[this.folderType]
      this.$emit('try-move-email', newFolder)
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
    },
  },
}
</script>

<style scoped>

</style>
