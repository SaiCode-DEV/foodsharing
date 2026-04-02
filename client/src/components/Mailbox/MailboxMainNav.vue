<template>
  <div>
    <div class="border">
      <div class="d-flex flex-wrap gap-1 p-2">
        <b-button
          v-if="page === MAILBOX_PAGE.READ_EMAIL || page === MAILBOX_PAGE.EMAIL_LIST"
          v-b-tooltip.hover
          :title="$t('mailbox.delete')"
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
          :text="$t('mailbox.reply.short')"
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
            {{ $t('mailbox.reply.short') }}
          </b-dropdown-item>
          <b-dropdown-item
            @click="showMailPage(MAIL_COMPOSITION_MODE.ANSWER_ALL)"
          >
            {{ $t('mailbox.reply_all') }}
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
          <i class="fas fa-share" /> {{ $t('mailbox.forward') }}
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
          {{ $t('mailbox.mark_all') }}
        </b-button>
        <b-button
          v-else-if="page === MAILBOX_PAGE.EMAIL_LIST"
          size="sm"
          variant="outline-primary"
          @click="mailboxViewClearSelected"
        >
          {{ $t('mailbox.mark_none') }}
        </b-button>
        <b-dropdown
          v-if="page === MAILBOX_PAGE.READ_EMAIL || page === MAILBOX_PAGE.EMAIL_LIST"
          id="dropdown-move-to"
          :text="$t('mailbox.move_to')"
          class=""
          size="sm"
          variant="outline-primary"
          :disabled="areMailsNotSelected && page === MAILBOX_PAGE.EMAIL_LIST"
        >
          <b-dropdown-item
            v-for="moveTarget in moveToTargets"
            :key="moveTarget.folder"
            @click="moveEmail(moveTarget.folder)"
          >
            {{ moveTarget.translation }}
          </b-dropdown-item>
        </b-dropdown>
        <!-- <div class="w-0 flex-grow-1" /> -->
        <b-button
          size="sm"
          variant="primary"
          class="ml-auto"
          @click="showMailPage(MAIL_COMPOSITION_MODE.NEW)"
        >
          {{ $t('mailbox.write') }}
        </b-button>
      </div>
    </div>
    <b-modal
      v-model="showEmailDeletionConfirmationModal"
      :title="$t('mailbox.conformation_modal.title')"
      :cancel-title="$t('globals.close')"
      @ok="mailboxEmailSingleViewDeleteEmail"
      @cancel="cancelEmailDeletion"
    >
      {{ $t('mailbox.conformation_modal.message') }}
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
      return this.isMarkedAsReadState ? this.$t('mailbox.mark_as_read') : this.$t('mailbox.mark_as_unread')
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
      }
    },
    isValidSender () {
      return this.selectedEmail.from.address?.length > 0
    },
    moveToTargets () {
      const folders = []
      if (this.folderType === MAILBOX_FOLDER.TRASH) {
        folders.push({ folder: MAILBOX_FOLDER.INBOX, translation: this.$t('mailbox.inbox') })
        folders.push({ folder: MAILBOX_FOLDER.SENT, translation: this.$t('mailbox.sent') })
      } else {
        folders.push({ folder: MAILBOX_FOLDER.TRASH, translation: this.$t('mailbox.trash') })
      }
      return folders
    },
  },
  created () {
    this.MAILBOX_PAGE = MAILBOX_PAGE
    this.MAIL_COMPOSITION_MODE = MAIL_COMPOSITION_MODE
  },
  methods: {
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
    moveEmail (targetFolder) {
      this.$emit('try-move-email', targetFolder)
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
    },
  },
}
</script>

<style scoped>

</style>
