/* eslint-disable eqeqeq,camelcase */
import { GET, goTo } from '@/browser'
import conversationStore from '@/stores/conversations'
import { requestStoreTeamMembership, declineStoreRequest } from '@/api/stores'
import i18n from '@/helper/i18n'
import { HTTP_RESPONSE } from './consts'
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

export function checkEmail (email) {
  const filter = /^([a-zA-Z0-9_.-])+@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/

  if (!filter.test(email)) {
    return false
  } else {
    return true
  }
}
export function img (photo, size) {
  if (photo) {
    if (photo.startsWith('/api/uploads/')) {
      // path for pictures uploaded with the new API
      if (size == undefined) {
        size = 75
      } else if (size === 'mini') {
        size = 35
      }

      return photo + `?w=${size}&h=${size}`
    } else if (photo.length > 3) {
      // backward compatible path for old pictures
      if (size == undefined) {
        size = 'med'
      }

      return `/images/${size}_q_${photo}`
    }
  }
  return `/img/${size}_q_avatar.png`
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

export async function wantToHelpStore (storeId, userId) {
  showLoader()

  try {
    await requestStoreTeamMembership(storeId, userId)
    pulseSuccess(i18n('store.request.got-it'))
  } catch (e) {
    if (e.code === HTTP_RESPONSE.UNPROCESSABLE_ENTITY) {
      pulseInfo(i18n('store.request.no-duplicate'))
    }
  }

  hideLoader()
}

export async function withdrawStoreRequest (storeId, userId) {
  showLoader()

  try {
    await declineStoreRequest(storeId, userId)
    pulseSuccess(i18n('store.request.withdrawn'))
  } catch (e) {

  }

  hideLoader()
}

export function checkAllCb (sel) {
  document.querySelectorAll("input[type='checkbox']").forEach(cb => {
    cb.checked = sel
  })
}

export function shuffle (o) {
  for (let j, x, i = o.length; i; j = Math.floor(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x);
  return o
}

Element.prototype.disableSelection = function () {
  this.onselectstart = function () { return false }
  this.unselectable = 'on'
  this.style.userSelect = 'none'
  return this
}
