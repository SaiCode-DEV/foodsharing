import { reactive } from 'vue'
import { REGION_UNIT_TYPE } from '@/stores/regions'

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

export const MAILBOX_ADDRESSBOOK_FILTER_TYPES = Object.freeze({
  REGIONS: [
    REGION_UNIT_TYPE.CITY,
    REGION_UNIT_TYPE.DISTRICT,
    REGION_UNIT_TYPE.REGION,
    REGION_UNIT_TYPE.BIG_CITY,
    REGION_UNIT_TYPE.COUNTRY,
    REGION_UNIT_TYPE.FEDERAL_STATE,
    REGION_UNIT_TYPE.PART_OF_TOWN,
  ],
  GROUPS: [REGION_UNIT_TYPE.WORKING_GROUP],
})

export const store = {
  state: reactive({
    page: null,
    answerMode: false,
    answerAll: false,
    selectedMailbox: [],
  }),
  setPage (value) {
    this.state.page = value
  },
  setAnswerMode (value, answerAll = false) {
    this.state.answerMode = value
    this.state.answerAll = answerAll
  },
  setMailbox (mailboxId, mailboxName, folderId) {
    this.state.selectedMailbox = [mailboxId, mailboxName, folderId]
  },
}
