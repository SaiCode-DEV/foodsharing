import { GET, goTo } from '@/browser'
import conversationStore from '@/stores/conversations'
import i18n from '@/helper/i18n'
import { initVueRouter } from '@/vue'
import Vue from 'vue'

export { goTo, GET }

export function chat (fsid) {
  conversationStore.openChatWithUser(fsid)
}

export function profile (id) {
  showLoader()
  goTo(`/profile/${id}`)
}

function definePulse (type, defaultTimeout = 5000, title, defaultIcon = 'fas fa-info-circle') {
  return (html, options = {}) => {
    let { duration, sticky } = options || {}
    if (typeof duration === 'undefined') duration = sticky ? -1 : defaultTimeout

    const notificationId = Date.now() + Math.floor(Math.random() * 1000)

    Vue.notify({
      id: notificationId,
      title: options.title || i18n(title),
      text: html,
      type, // 'info', 'success', or 'error'
      duration,
      closeOnClick: false,
      pauseOnHover: type === 'warn' || type === 'error',
      data: {
        icon: options.icon || defaultIcon,
        details: options.details,
        pre: options?.pre,
      },
    })

    // Return notification ID in case caller needs to close it programmatically
    return notificationId
  }
}

export const pulseInfo = definePulse('info', 7000, 'notifications.info', 'fas fa-info-circle')
export const pulseSuccess = definePulse('success', 5000, 'notifications.success', 'fas fa-check-circle')
export const pulseWarning = definePulse('warn', 10000, 'notifications.warning', 'fas fa-exclamation-triangle')
export const pulseError = definePulse('error', 20000, 'notifications.error', 'fas fa-exclamation-circle')

export function closeNotification (id) {
  Vue.notify.close(id)
}

export function reload () {
  window.location.reload()
}

export function showLoader () {
  window.showLoading()
}
export function hideLoader () {
  window.hideLoading()
}

export function shuffle (o) {
  for (let j, x, i = o.length; i; j = Math.floor(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x);
  return o
}

// Initialize Vue Router (replaces the old page loading)
if (document.querySelector('#app')) {
  initVueRouter('#app')
}
