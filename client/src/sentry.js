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
