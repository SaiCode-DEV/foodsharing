import axios from 'axios'
import { HTTP_RESPONSE } from '@/consts'
import { url } from '@/helper/urls'
import { captureRequestError, handleNetworkError } from '@/sentry'

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

// Response interceptor for retries
api.interceptors.response.use(null, async error => {
  const config = error.config

  if (error.response?.status === HTTP_RESPONSE.UNAUTHORIZED && !config.disableLoginRedirect) {
    window.location = url('login')
    return Promise.reject(error)
  } else if (error.response?.status === HTTP_RESPONSE.TOO_MANY_REQUESTS) {
    return Promise.reject(error)
  }

  if (!config || config.__retryCount >= 2) {
    return Promise.reject(error)
  }

  config.__retryCount = (config.__retryCount || 0) + 1
  captureRequestError(error, { path: config.url, attempt: config.__retryCount })

  await new Promise(resolve => setTimeout(resolve, 1000 * Math.pow(2, config.__retryCount - 1)))
  return api(config)
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
    path: error.config?.url,
    options: error.config,
    attempt: error.config?.__retryCount,
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

export const get = (path, params) => request(path, { method: 'GET', params })
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
