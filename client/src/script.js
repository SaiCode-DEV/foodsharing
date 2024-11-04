/* eslint-disable eqeqeq,camelcase */

import $ from 'jquery'

import 'jquery-slimscroll'

import { GET, goTo, isMob } from '@/browser'
import conversationStore from '@/stores/conversations'
import { requestStoreTeamMembership, declineStoreRequest } from '@/api/stores'
import i18n from '@/helper/i18n'
import { HTTP_RESPONSE } from './consts'

export { goTo, isMob, GET }

export function collapse_wrapper (id) {
  const $content = $(`#${id}-wrapper .element-wrapper`)
  const $label = $(`#${id}-wrapper .wrapper-label i`)
  if ($content.is(':visible')) {
    $content.hide()
    $label.removeClass('fa-caret-down').addClass('fa-caret-right')
  } else {
    $content.show()
    $label.removeClass('fa-caret-right').addClass('fa-caret-down')
  }
}

export function initialize () {
  $(function () {
    $('#main').css('display', 'block')

    if (!isMob()) {
      $('#main a').tooltip({
        show: false,
        hide: false,
        content: function () {
          return $(this).attr('title')
        },
        position: {
          my: 'center bottom-20',
          at: 'center top',
          using: function (position, feedback) {
            $(this).css(position)
            $('<div>')
              .addClass('arrow')
              .addClass(feedback.vertical)
              .addClass(feedback.horizontal)
              .appendTo(this)
          },
        },
      })
    }

    $('.dialog').dialog()

    $('ul.toolbar li').on('mouseenter', function () {
      $(this).addClass('ui-state-hover')
    }).on('mouseleave', function () {
      $(this).removeClass('ui-state-hover')
    })

    $('.text, .textarea, select').on('focus', function () {
      $(this).addClass('focus')
    })
    $('.text, .textarea, select').on('blur', function () {
      $(this).removeClass('focus')
    })

    $('.value').on('blur', function () {
      const el = $(this)
      if (el.val() != '') {
        el.removeClass('input-error')
      }
    })
  })
}

export function chat (fsid) {
  conversationStore.openChatWithUser(fsid)
}

export function profile (id) {
  showLoader()
  goTo(`/profile/${id}`)
}

function displayXhrMessages (msg) {
  for (let i = 0; i < msg.length; i++) {
    switch (msg[i].type) {
      case 'error':
        pulseError(msg[i].text); break
      case 'success':
        pulseSuccess(msg[i].text); break
      default:
        pulseInfo(msg[i].text); break
    }
  }
}

export const ajax = {
  data: {},
  req: function (app, method, option) {
    let opt = {}
    if (option != undefined) {
      opt = option
    }

    if (opt.method == undefined) {
      opt.method = 'get'
    }

    if (opt.loader == undefined || opt.loader == true) {
      opt.loader = true
      showLoader()
    }

    if (opt.data == undefined) {
      opt.data = {}
    }

    return $.ajax({
      url: `/xhrapp?app=${app}&m=${method}`,
      data: opt.data,
      dataType: 'json',
      method: opt.method,
      success: function (ret) {
        if (ret.status == 1) {
          if (ret.msg != undefined) {
            displayXhrMessages(ret.msg)
          }

          if (ret.append != undefined) {
            $(ret.append).html(ret.html)
          }

          if (ret.script != undefined) {
            if (ret.data != undefined) {
              ajax.data = ret.data
            }
            $.globalEval(ret.script)
          }

          if (opt.success != undefined) {
            opt.success(ret.data)
          }
        }
      },
      fail: function (request) {
        if (request.status === 403) {
          pulseError(i18n('error_permissions'))
        }
      },
      complete: function () {
        if (opt.loader === true) {
          hideLoader()
        }
        if (opt.complete != undefined) {
          opt.complete()
        }
      },
    })
  },
}
export function ajreq (name, options, method, app) {
  options = typeof options !== 'undefined' ? options : {}
  return ajax.req(options.app || app || GET('page'), name, {
    method: method,
    data: options,
    loader: options.loader,
  })
}

function definePulse (type, defaultTimeout = 5000) {
  return (html, options = {}) => {
    let { timeout, sticky } = options || {}
    if (typeof timeout === 'undefined') timeout = sticky ? 900000 : defaultTimeout
    const animationDuration = Math.min(timeout, 400)
    const el = $(`#pulse-${type}`)
    el.html(html).stop().fadeIn(animationDuration)
    const hide = () => {
      el.stop().fadeOut(animationDuration)
      $(document).off('click', hide)
      clearTimeout(timer)
    }
    const timer = setTimeout(hide, timeout)
    setTimeout(() => {
      $(document).on('click', hide)
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
  $("input[type='checkbox']").prop('checked', sel)
}

export function shuffle (o) {
  for (let j, x, i = o.length; i; j = Math.floor(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x);
  return o
}

export function session_id () {
  return /SESS\w*ID=([^;]+)/i.test(document.cookie) ? RegExp.$1 : false
}

$.fn.extend({
  disableSelection: function () {
    return this.each(function () {
      this.onselectstart = function () { return false }
      this.unselectable = 'on'
      $(this).css('user-select', 'none')
    })
  },
})
