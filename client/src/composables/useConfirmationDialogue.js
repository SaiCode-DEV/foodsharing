import EventEmitter from 'eventemitter3'
import i18n from '@/helper/i18n'

const emitter = new EventEmitter()

const defaultOptions = {
  title: i18n('are_you_sure'),
  okVariant: 'danger',
  okTitle: i18n('button.delete'),
  cancelTitle: i18n('button.cancel'),
  centered: true,
  params: {},
  countdown: 0,
}

export default function useConfirmationDialogue () {
  const confirmationDialogue = (messageKey, options = {}) => {
    options = { ...defaultOptions, ...options }

    return new Promise((resolve) => {
      emitter.emit('show-confirmation', {
        message: i18n(messageKey, options.params),
        ...options,
        onConfirm: () => resolve(true),
        onCancel: () => resolve(false),
      })
    })
  }

  return {
    emitter,
    confirmationDialogue,
  }
}
