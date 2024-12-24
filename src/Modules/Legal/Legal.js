import '@/core'
import '@/globals'
import i18n from '@/helper/i18n'

const noticeId = '#legal_form_privacyNoticeAcknowledged'
const form = document.querySelector('form[name="legal_form"]')
const doNotAgree = '0'

form.addEventListener('submit', function (event) {
  const noticeElement = document.querySelector(noticeId)
  if (noticeElement && noticeElement.value === doNotAgree) {
    if (!confirm(i18n('legal.are_you_sure_to_downgrade'))) {
      event.preventDefault()
    }
  }
})
