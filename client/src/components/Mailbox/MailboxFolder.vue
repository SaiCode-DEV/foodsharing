<template>
  <div
    class="mailbox-content my-2 "
    :class="mailboxClass"
  >
    <div
      v-b-tooltip.hover.right
      :title="fullName"
      class="d-inline-block pr-2"
    >
      <i class="fas fa-inbox" />
      {{ mailboxName }}
      <b-badge
        v-if="unreadCount"
        pill
        variant="primary"
      >
        {{ unreadCount }}
      </b-badge>
    </div>

    <div class="ml-4">
      <b-link
        :class="folderClassInbox"
        @click.prevent="setMailboxIdAndFolder(MAILBOX_FOLDER.INBOX)"
      >
        {{ $i18n('mailbox.inbox') }}
      </b-link>
      ·
      <b-link
        :class="folderClassSent"
        @click.prevent.stop="setMailboxIdAndFolder(MAILBOX_FOLDER.SENT)"
      >
        {{ $i18n('mailbox.sent') }}
      </b-link>
      ·
      <b-link
        :class="folderClassTrash"
        @click.prevent.stop="setMailboxIdAndFolder(MAILBOX_FOLDER.TRASH)"
      >
        {{ $i18n('mailbox.trash') }}
      </b-link>
    </div>
  </div>
</template>

<script>
import { store, MAILBOX_PAGE, MAILBOX_FOLDER } from '@/stores/mailbox'

export default {
  props: {
    mailboxId: { type: Number, required: true },
    mailboxName: { type: String, required: true },
    fullName: { type: String, default: '' },
    unreadCount: { type: Number, default: 0 },
  },
  data () {
    return {
      mailboxMails: [],
    }
  },
  computed: {
    mailboxClass () {
      return store.state.selectedMailbox[0] === this.mailboxId ? 'mailbox-selected' : ''
    },
    folderClassInbox () {
      return store.state.selectedMailbox[0] === this.mailboxId && store.state.selectedMailbox[2] === MAILBOX_FOLDER.INBOX ? 'selected-folder' : ''
    },
    folderClassSent () {
      return store.state.selectedMailbox[0] === this.mailboxId && store.state.selectedMailbox[2] === MAILBOX_FOLDER.SENT ? 'selected-folder' : ''
    },
    folderClassTrash () {
      return store.state.selectedMailbox[0] === this.mailboxId && store.state.selectedMailbox[2] === MAILBOX_FOLDER.TRASH ? 'selected-folder' : ''
    },
    selectedMailbox () {
      return store.state.selectedMailbox
    },
  },
  created () {
    this.MAILBOX_FOLDER = MAILBOX_FOLDER
  },
  methods: {
    setMailboxIdAndFolder (folderId) {
      store.setPage(MAILBOX_PAGE.EMAIL_LIST)
      store.setMailbox(this.mailboxId, this.mailboxName, folderId)
    },
  },
}
</script>

<style lang="scss" scoped>
.mailbox-selected {
  background-color: var(--fs-color-primary-200);
  border-radius: 10px;
}

.selected-folder {
  color: var(--fs-color-success-600);
}
</style>
