/**
 * This is a lightweight api client for the service worker.
 * It must not import any other modules that could transitively depend on the "window" object.
 */

import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json; charset=utf-8',
  },
})

// Request interceptor for CSRF token
api.interceptors.request.use(config => {
  const match = document.cookie?.match(/CSRF_TOKEN=([0-9a-f]+)/)
  if (match) {
    config.headers['X-CSRF-Token'] = match[1]
  }
  return config
})

export class HTTPError extends Error {
  constructor (error) {
    super(error.message)
    this.code = error.response?.status
    this.statusText = error.response?.statusText
    this.jsonContent = error.response?.data
  }
}

export const request = async (path, options = {}) => {
  try {
    const { data } = await api(path, options)
    return data
  } catch (error) {
    console.error('API request failed in service worker:', error)
    throw new HTTPError(error)
  }
}

export const get = (path, config) => request(path, { method: 'GET', ...config })
export const post = (path, data, config = {}) => request(path, { method: 'POST', data, ...config })
