export default {
  methods: {
    confirmationDialogue (messageKey, options = {}) {
      options = Object.assign({
        title: this.$i18n('are_you_sure'),
        okVariant: 'danger',
        okTitle: this.$i18n('button.delete'),
        cancelTitle: this.$i18n('button.cancel'),
        centered: true,
        params: {},
      }, options)
      return this.$bvModal.msgBoxConfirm(this.$i18n(messageKey, options.params), options)
    },
  },
}
