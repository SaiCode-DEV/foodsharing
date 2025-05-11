import axios from 'axios'
import { HTTP_RESPONSE } from '@/consts'
import { url } from '@/helper/urls'
import { captureRequestError, handleNetworkError } from '@/sentry'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-cache, no-store, must-revalidate',
    Pragma: 'no-cache',
    Expires: '0',
  },
})

let activeRequests = 0

// Request counter interceptors
api.interceptors.request.use(config => {
  activeRequests++
  return config
})

api.interceptors.response.use(
  response => {
    activeRequests--
    return response
  },
  error => {
    activeRequests--
    return Promise.reject(error)
  },
)
const escapeHtml = (str) => String(str).replace(/[&<>"']/g, (char) => {
  const escapeChars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }
  return escapeChars[char] || char
})

const showNetworkError = (key, error) => {
  const code = escapeHtml(error.response?.status || error.code)
  const message = escapeHtml(error.message ?? 'N/A')
  const text = i18n('net_errors.' + key) + '\n' + i18n('net_errors.code') + code + '\n' + i18n('net_errors.message') + message
  console.error(text, error)
  pulseError(text)
}

const knownNetworkCodes = [
  'ERR_NETWORK',
  'ECONNABORTED',
  'ERR_NETWORK_CHANGED',
  'ERR_BAD_RESPONSE',
  'ERR_CANCELED',
]

// Memorize the page navigation state
let isPageNavigation = false
window.onbeforeunload = function () {
  isPageNavigation = true
  return undefined
}

// Response interceptor for retries
api.interceptors.response.use(null, async error => {
  const config = error.config
  let isUnknownError = true
  let reportToSentry = false

  // If the page is reloading or the user is navigating back/forward,
  // interrupting API events is expected and we don't want to show an error
  // message
  if (isPageNavigation) {
    return Promise.reject(error)
  }

  if (error.response?.status === HTTP_RESPONSE.UNAUTHORIZED) {
    // Unauthorized -> redirect to login unless disabled (e.g. on failed logins
    // as this would otherwise reload the page without need)
    if (!config.disableLoginRedirect) {
      window.location = url('login')
    }
    isUnknownError = false
  } else if (error.response?.status === HTTP_RESPONSE.TOO_MANY_REQUESTS) {
    // Too many requests
    showNetworkError('TOO_MANY_REQUESTS', error)
    isUnknownError = false
  } else if (error.response?.status === HTTP_RESPONSE.NOT_MODIFIED) {
    // Not modified
    // Not an error so nothing shown
    isUnknownError = false
  } else if (error.response?.status === HTTP_RESPONSE.FORBIDDEN) {
    // Forbidden
    showNetworkError('FORBIDDEN', error)
    isUnknownError = false
  } else if (error.response?.status === HTTP_RESPONSE.NOT_FOUND) {
    // Not found
    showNetworkError('NOT_FOUND', error)
    isUnknownError = false
    reportToSentry = true
  } else if (error.response?.status >= HTTP_RESPONSE.BAD_REQUEST &&
             error.response?.status < HTTP_RESPONSE.INTERNAL_SERVER_ERROR) {
    // Catch all other client errors (4xx)
    showNetworkError('BAD_REQUEST', error)
    isUnknownError = false
    reportToSentry = true
  } else if (error.response?.status >= HTTP_RESPONSE.INTERNAL_SERVER_ERROR) {
    // Catch all other server errors (5xx)
    showNetworkError('SERVER_ERROR', error)
    isUnknownError = false
    reportToSentry = true
  } else if (knownNetworkCodes.includes(error.code)) {
    // Network errors without a response
    showNetworkError(error.code, error)
    isUnknownError = false
  }

  if (config && reportToSentry) {
    // Report to sentry
    captureRequestError(error, { path: config.url, options: config })
  }

  if (isUnknownError) {
    // Show error message
    showNetworkError('unknown', error)
  }
  return Promise.reject(error)
})

// Request interceptor for CSRF token
api.interceptors.request.use(config => {
  const match = document.cookie?.match(/CSRF_TOKEN=([0-9a-f]+)/)
  if (match) {
    config.headers['X-CSRF-Token'] = match[1]
  }
  return config
})

// Error transformer
api.defaults.transformResponse = [...(axios.defaults.transformResponse || []), data => {
  return data
}]

export class HTTPError extends Error {
  constructor (error) {
    super(error.message)
    this.code = error.response?.status
    this.statusText = error.response?.statusText
    this.jsonContent = error.response?.data
  }
}

const handleError = error => {
  handleNetworkError(error, {
    path: error?.config?.url,
    options: error?.config,
  })
  throw new HTTPError(error)
}

export const request = async (path, options = {}) => {
  try {
    const { data } = await api(path, options)
    return data
  } catch (error) {
    handleError(error)
  }
}

export const get = (path, config) => request(path, { method: 'GET', ...config })
export const post = (path, data, config = {}) => request(path, { method: 'POST', data, ...config })
export const put = (path, data) => request(path, { method: 'PUT', data })
export const patch = (path, data) => request(path, { method: 'PATCH', data })
export const remove = (path, data) => request(path, { method: 'DELETE', data })

// Export method to check for active requests
export const hasActiveRequests = () => activeRequests > 0

// Make it accessible for Codeception tests
if (typeof window !== 'undefined') {
  window.hasActiveRequests = hasActiveRequests
}
