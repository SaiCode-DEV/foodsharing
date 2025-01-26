import Vue from 'vue'
import * as Sentry from '@sentry/vue'
import serverData from '@/helper/server-data'

if (serverData.ravenConfig) {
  console.log('using sentry config from server', serverData.ravenConfig)
  Sentry.init({
    Vue: Vue,
    attachProps: true,
    logErrors: true,
    dsn: serverData.ravenConfig,
    integrations: [
      Sentry.captureConsoleIntegration({
        levels: ['error'],
      }),
    ],
  })
}

export function captureError (error) {
  if (typeof error === 'string') {
    error = new Error(error)
  }
  console.error(error)
  Sentry.captureException(error)
  return error
}

export function captureRequestError (error, { path, options, attempt }) {
  Sentry.withScope((scope) => {
    if (error.name === 'AbortError') {
      scope.setExtra('path', path)
      scope.setExtra('options', options)
      scope.setExtra('attempt', attempt)
      Sentry.captureException(new Error('Request timeout'))
      return
    }

    if (error instanceof TypeError && error.message.includes('Failed to fetch')) {
      scope.setExtra('path', path)
      scope.setExtra('options', options)
      scope.setExtra('attempt', attempt)
      scope.setTag('device', /iPhone|iPad|iPod|Safari/i.test(navigator.userAgent) ? 'apple' : 'other')
      Sentry.captureException(error)
      return
    }

    // Add any other error details
    scope.setExtra('path', path)
    scope.setExtra('options', options)
    scope.setExtra('attempt', attempt)
    scope.setExtra('errorType', error.constructor.name)
    if (error.code) scope.setExtra('statusCode', error.code)
    if (error.statusText) scope.setExtra('statusText', error.statusText)
    if (error.jsonContent) scope.setExtra('jsonContent', error.jsonContent)

    Sentry.captureException(error)
  })
}

export function isNetworkError (error) {
  return error.message === 'Network Error'
}

export function isTimeoutError (error) {
  return error.code === 'ECONNABORTED'
}

export function isUserAbortError (error) {
  return error.code === 'ERR_CANCELED' || error.message === 'Request aborted'
}

export function handleNetworkError (error, { path, options, attempt }) {
  if (isNetworkError(error)) {
    console.warn('Client network error - not reporting to Sentry')
    throw new Error('Network connection error')
  }

  if (isTimeoutError(error)) {
    const timeoutDuration = options.timeout / 1000
    console.warn(`Request timeout after ${timeoutDuration}s - not reporting to Sentry`)
    throw new Error(`Request timed out after ${timeoutDuration} seconds`)
  }

  if (isUserAbortError(error)) {
    console.warn('Request aborted by user - not reporting to Sentry')
    throw new Error('Request cancelled')
  }

  // Only capture server errors
  if (error.response) {
    captureRequestError(error, { path, options, attempt })
  }

  return error
}
