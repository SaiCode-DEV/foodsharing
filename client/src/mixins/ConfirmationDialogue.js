import i18n from '@/helper/i18n'

export default {
  methods: {
    confirmationDialogue (messageKey, options = {}) {
      options = Object.assign({
        title: i18n('are_you_sure'),
        okVariant: 'danger',
        okTitle: i18n('button.delete'),
        cancelTitle: i18n('button.cancel'),
        centered: true,
        params: {},
      }, options)
      return this.$bvModal.msgBoxConfirm(i18n(messageKey, options.params), options)
    },
  },
}
