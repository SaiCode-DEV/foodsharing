import { ajreq } from '@/script'
import DataUser from '@/stores/user'
// this last call imports the text in the respective languages

export function getBrowserLocation (success) {
  if (DataUser.getters.isLoggedIn()) {
    return success(DataUser.getters.getLocations())
  }
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      ajreq('savebpos', {
        app: 'map',
        lat: pos.coords.latitude,
        lon: pos.coords.longitude,
      })
      success({
        lat: pos.coords.latitude,
        lon: pos.coords.longitude,
      })
    })
  }
}

/**
 * Make things available globally via the browser window object
 *
 * This is to allow functions to be called from non-webpack javascript.
 * e.g. click handlers, or xhr javascript responses
 *
 * @param data object of things to expose globally
 */
export function expose (data) {
  Object.assign(window, data)
}

/**
 * Compare function used in sorting of btable
 */

export function optimizedCompare (a, b, key) {
  const noLocale = /^[\w-.\s,]*$/
  const elemA = a[key]
  const elemB = b[key]
  if (typeof elemA === 'number' || (noLocale.test(elemA) && noLocale.test(elemB))) {
    if (typeof elemA === 'string') {
      const a = elemA.toLowerCase()
      const b = elemB.toLowerCase()
      return (a > b ? 1 : (a === b ? 0 : -1))
    }
    return (elemA > elemB ? 1 : (elemA === elemB ? 0 : -1))
  } else {
    return elemA.localeCompare(elemB)
  }
}

export const generateQueryString = params => {
  const qs = Object.keys(params)
    .filter(key => params[key] !== '')
    .map(key => key + '=' + params[key])
    .join('&')
  return qs.length ? `?${qs}` : ''
}

function autoLink (text) {
  const pattern = /(^|\s)((?:https?|ftp):\/\/([-A-Z0-9+\u0026@#/%?=()~_|!:,.;]*[-A-Z0-9+\u0026@#/%=~()_|]))/gi
  const currentHost = document.location.host

  return text.replace(pattern, function (match, space, url, urlWithoutProto) {
    return `${space}<a href="${url}" ${urlWithoutProto.split('/', 2)[0] !== currentHost ? ' target="_blank"' : ''}>${urlWithoutProto}</a>`
  })
}

function nl2br (str) {
  const breakTag = '<br>'
  return (`${str}`).replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, `$1${breakTag}$2`)
}

export function plainToHtml (string) {
  const entityMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
  }
  return autoLink(nl2br(String(string).replace(/[&<>]/g, function fromEntityMap (s) {
    return entityMap[s]
  })))
}

export function plainToHtmlAttribute (string) {
  const entityMap = {
    '"': '&quot',
    "'": '&#39;',
  }
  return String(string).replace(/["']/g, function fromEntityMap (s) {
    return entityMap[s]
  },
  )
}

export function isWebGLSupported () {
  // https://stackoverflow.com/a/22953053
  try {
    const canvas = document.createElement('canvas')
    return !!window.WebGLRenderingContext &&
      (canvas.getContext('webgl') || canvas.getContext('experimental-webgl'))
  } catch (e) {
    return false
  }
}

export function debounce (func, timeout = 300) {
  let timer
  return (...args) => {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => func.apply(this, args), timeout)
  }
}

export function throttle (func, timeout = 300) {
  let throttled = false
  return (...args) => {
    if (!throttled) {
      func.apply(this, args)
      throttled = true
      setTimeout(() => {
        throttled = false
      }, timeout)
    }
  }
}

/**
 * returns true if the array contents and its order are equal. [1, 2, 3] !== [3, 2, 1]
 * @param {[]} a
 * @param {[]} b
 * @returns {boolean}
 */
export function arrayEquals (a, b) {
  return a.length === b.length && a.every((val, index) => val === b[index])
}

/**
 * returns true if the array contents are equal. [1, 2, 3] === [3, 2, 1]
 * @param {[]} a
 * @param {[]} b
 * @returns {boolean}
 */
export function arrayContentEquals (a, b) {
  return a.length === b.length && a.every((val, index) => b.includes(val))
}
