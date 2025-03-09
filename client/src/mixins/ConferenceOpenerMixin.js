import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

const { confirmationDialogue } = useConfirmationDialogue()

export default {
  data: function () {
    return {
      conferenceId: null,
    }
  },
  methods: {
    async showConferencePopup (id) {
      const dialogueOptions = {
        title: this.$i18n('conference.join_title'),
        okTitle: this.$i18n('conference.join'),
        okVariant: undefined,
      }
      if (!await confirmationDialogue('conference.confirm_info', dialogueOptions)) return
      this.conferenceId = id
      this.join()
    },
    join () {
      window.open(`/api/groups/${this.conferenceId}/conference?redirect=true`)
    },
  },
}
