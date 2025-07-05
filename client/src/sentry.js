import Vue from 'vue'
import * as Sentry from '@sentry/vue'
import serverData from '@/helper/server-data'
import { useEnvironmentCheck } from '@/composables/useEnvironmentCheck'
import { pulseError } from '@/script'

const { isBeta } = useEnvironmentCheck()

if (serverData.ravenConfig) {
  // Initialize Sentry
  Sentry.init({
    Vue,
    attachProps: true,
    logErrors: true,
    release: serverData.version || 'unknown',
    dsn: serverData.ravenConfig,
    transport: Sentry.makeBrowserOfflineTransport(Sentry.makeFetchTransport),
    integrations: [
      Sentry.browserTracingIntegration(),
      Sentry.captureConsoleIntegration({
        levels: ['error'],
      }),
    ],
    attachStacktrace: true,
    tracesSampleRate: isBeta ? 1.0 : 0.01,
    replaysSessionSampleRate: isBeta ? 1 : 0,
    replaysOnErrorSampleRate: 1.0,
  })

  // Set user context
  if (serverData?.user) {
    Sentry.setUser({
      id: serverData.user.id,
      username: serverData.user.firstname,
    })
  }
}

export function captureError (error) {
  if (typeof error === 'string') {
    error = new Error(error)
  }
  console.error(error)
  Sentry.captureException(error)
  return error
}

export async function captureFeedback (feedback, attachments) {
  try {
    const response = await Sentry.sendFeedback(feedback, {
      attachments,
    })
    console.log('Feedback ', response)
    return true
  } catch (error) {
    pulseError(error)
    // console.error('Feedback Error', error)
    return false
  }
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
