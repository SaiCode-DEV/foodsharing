import axios from 'axios'
import { HTTP_RESPONSE } from '@/consts'
import { url } from '@/helper/urls'
import { captureRequestError } from '@/sentry'
import { pulseError } from '@/script'
import { getCache, setCache, getCacheInterval } from '@/helper/cache'
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

const showNetworkError = (key, error) => {
  const code = error.response?.status || error.code
  const message = error.message ?? 'N/A'
  const path = error?.config?.url ?? 'N/A'
  const action = (error?.config?.method ?? '').toUpperCase()
  let pre = action + ' ' + path + '\n' + JSON.stringify(error.response?.data) || 'N/A'
  const text = i18n('net_errors.' + key)
  const details = i18n('net_errors.code') + code + '\n' + i18n('net_errors.message') + message + '\n' + i18n('net_errors.action_response')
  console.error(text, error)

  // Abbreviate the pre text if it's too long
  if (pre.length > 250) {
    pre = pre.substring(0, 200) + ' [...] ' + pre.substring(pre.length - 50)
  }

  let title = ''
  let icon = 'fas fa-wifi'
  switch (true) {
    case error.response?.status >= 500:
      title = i18n('net_errors.title.500')
      icon = 'fas fa-server'
      break
    case error.response?.status >= 400:
      title = i18n('net_errors.title.400')
      icon = 'fas fa-exclamation-triangle'
      break
    default:
      title = i18n('net_errors.title.general')
      break
  }

  pulseError(text, {
    title,
    icon,
    duration: 10000,
    details,
    pre,
  })
}

function showKnownError (translationKey, icon = 'fas fa-info-circle') {
  const title = i18n(`network_errors.${translationKey}.title`)
  const text = i18n(`network_errors.${translationKey}.text`)

  pulseError(text, {
    title,
    icon,
    duration: 50000,
  })
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

  // Otherwise, check for error codes we do want to report here
  const skipErrors = error.config?.skipErrorNotificationFor || []
  if (skipErrors.includes(error.response?.status)) {
    return Promise.reject(error)
  }

  // first check for known errors. if so, show network_errors.<code>.title/text
  for (const knownError of KNOWN_ERRORS) {
    if (error.response?.status === knownError.code &&
        error.response?.data?.message === knownError.message) {
      showKnownError(knownError.translationKey, knownError.icon)
      return Promise.reject(error)
    }
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
  const match = document.cookie?.match(/FS_CSRF_TOKEN=([0-9a-f]+)/)
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

export const request = async (path, options = {}) => {
  // Accept skipErrorNotificationFor in options and pass to axios config
  const axiosOptions = { ...options }
  if (options.skipErrorNotificationFor) {
    axiosOptions.skipErrorNotificationFor = options.skipErrorNotificationFor
  }
  try {
    const { data } = await api(path, axiosOptions)
    return data
  } catch (error) {
    // Attach skipErrorNotificationFor to error for interceptor
    if (options.skipErrorNotificationFor) {
      error.config = error.config || {}
      error.config.skipErrorNotificationFor = options.skipErrorNotificationFor
    }
    throw new HTTPError(error)
  }
}

export const get = (path, config) => request(path, { method: 'GET', ...config })
export const post = (path, data, config = {}) => request(path, { method: 'POST', data, ...config })
export const put = (path, data, config = {}) => request(path, { method: 'PUT', data, ...config })
export const patch = (path, data, config = {}) => request(path, { method: 'PATCH', data, ...config })
export const remove = (path, data, config = {}) => request(path, { method: 'DELETE', data, ...config })

// Cached GET with automatic cache handling
// Usage: cachedGet('/api/path', { cacheKey: cacheKey('pickupOptions'), cacheDuration: 300000 })
const activeCacheRequests = new Map()

export const cachedGet = async (path, { cacheKey, cacheDuration = 300000, force = false, ...config } = {}) => {
  if (!cacheKey) {
    throw new Error('cachedGet requires a cacheKey')
  }

  // Check if this exact cache key is already being fetched
  if (activeCacheRequests.has(cacheKey)) {
    return activeCacheRequests.get(cacheKey)
  }

  const fetchPromise = (async () => {
    try {
      const shouldRefetch = force || await getCacheInterval(cacheKey, cacheDuration)

      if (shouldRefetch) {
        const data = await get(path, config)
        await setCache(cacheKey, data)
        return data
      } else {
        return await getCache(cacheKey)
      }
    } finally {
      activeCacheRequests.delete(cacheKey)
    }
  })()

  activeCacheRequests.set(cacheKey, fetchPromise)
  return fetchPromise
}

// Export method to check for active requests
export const hasActiveRequests = () => activeRequests > 0

// Make it accessible for Codeception tests
if (typeof window !== 'undefined') {
  window.hasActiveRequests = hasActiveRequests
}

const KNOWN_ERRORS = Object.freeze([
  { message: 'CSRF Failed: CSRF token missing or incorrect.', code: 400, translationKey: 'csrf_token_invalid', icon: 'fas fa-shield-alt' },
])
