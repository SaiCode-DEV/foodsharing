import { reactive } from 'vue'
import { REGION_UNIT_TYPE } from '@/stores/regions'
import { getMailboxes } from '@/api/mailbox'
import { useUserStore } from '@/stores/user'

export const MAILBOX_PAGE = Object.freeze({
  EMAIL_LIST: 1,
  READ_EMAIL: 2,
  NEW_EMAIL: 3,
})

export const MAILBOX_FOLDER = Object.freeze({
  INBOX: 1,
  SENT: 2,
  TRASH: 3,
})

export const MAIL_COMPOSITION_MODE = Object.freeze({
  NEW: 1,
  ANSWER: 2,
  ANSWER_ALL: 3,
  FORWARD: 4,
})

export const MAILBOX_ADDRESSBOOK_FILTER_TYPES = Object.freeze({
  REGIONS: [
    REGION_UNIT_TYPE.CITY,
    REGION_UNIT_TYPE.DISTRICT,
    REGION_UNIT_TYPE.REGION,
    REGION_UNIT_TYPE.BIG_CITY,
    REGION_UNIT_TYPE.COUNTRY,
    REGION_UNIT_TYPE.FEDERAL_STATE,
    REGION_UNIT_TYPE.PART_OF_TOWN,
    REGION_UNIT_TYPE.CONTINENT,
  ],
  GROUPS: [REGION_UNIT_TYPE.WORKING_GROUP],
})

export const MAX_NUMBER_OF_EMAIL_ATTACHMENTS = 10

export const store = {
  state: reactive({
    page: null,
    compositionMode: MAIL_COMPOSITION_MODE.NONE,
    selectedMailbox: [],
    mailboxes: [],
  }),
  setPage (value) {
    this.state.page = value
  },
  setCompositionMode (value) {
    this.state.compositionMode = value
  },
  setMailbox (mailboxId, mailboxName, folderId) {
    this.state.selectedMailbox = [mailboxId, mailboxName, folderId]
  },
  setMailboxes (mailboxes) {
    this.state.mailboxes = mailboxes
  },
  async fetchMailboxes () {
    const mailboxes = await getMailboxes()
    this.state.mailboxes = mailboxes
    await useUserStore().updateMailUnreadCount(mailboxes.reduce((total, mailbox) => total + mailbox.count, 0))
  },
}
