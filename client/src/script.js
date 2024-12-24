/* eslint-disable eqeqeq,camelcase */
import { GET, goTo, isMob } from '@/browser'
import conversationStore from '@/stores/conversations'
import { requestStoreTeamMembership, declineStoreRequest } from '@/api/stores'
import i18n from '@/helper/i18n'
import { HTTP_RESPONSE } from './consts'

export { goTo, isMob, GET }

export function chat (fsid) {
  conversationStore.openChatWithUser(fsid)
}

export function profile (id) {
  showLoader()
  goTo(`/profile/${id}`)
}

function definePulse (type, defaultTimeout = 5000) {
  return (html, options = {}) => {
    let { timeout, sticky } = options || {}
    if (typeof timeout === 'undefined') timeout = sticky ? 900000 : defaultTimeout
    const el = document.querySelector(`#pulse-${type}`)
    el.innerHTML = html
    el.style.display = 'block'
    const hide = () => {
      el.style.display = 'none'
      document.removeEventListener('click', hide)
      clearTimeout(timer)
    }
    const timer = setTimeout(hide, timeout)
    setTimeout(() => {
      document.addEventListener('click', hide)
    }, 500)
  }
}

export const pulseInfo = definePulse('info', 4000)
export const pulseSuccess = definePulse('success', 5000)
export const pulseError = definePulse('error', 6000)

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
    } else {
      console.error(e.code)
      pulseError(i18n('error_unexpected'))
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
    pulseError(i18n('error_unexpected'))
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

export function session_id () {
  return /SESS\w*ID=([^;]+)/i.test(document.cookie) ? RegExp.$1 : false
}

Element.prototype.disableSelection = function () {
  this.onselectstart = function () { return false }
  this.unselectable = 'on'
  this.style.userSelect = 'none'
  return this
}
