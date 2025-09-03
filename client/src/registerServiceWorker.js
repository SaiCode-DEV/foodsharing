import { Workbox } from 'workbox-window'

function registerServiceWorker () {
  document.addEventListener('DOMContentLoaded', () => {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service workers are not supported by this browser')
      return
    }

    // Increment SW_VERSION to force clients to fetch a fresh sw.js (cache busting)
    const workbox = new Workbox(`/sw.js?v=${new Date().getDate()}`)

    workbox.addEventListener('installed', event => {
      if (event.isUpdate) {
        console.log('[SW] Service worker updated')
      }
    })

    workbox.addEventListener('ready', () => {
      console.log('[SW] Service worker is ready.')
    })

    workbox.register().catch(error => {
      console.warn('[SW] Service worker registration failed:', error)
    })

    // Periodically check for updates (e.g. every 60 minutes)
    const UPDATE_INTERVAL_MINUTES = 60
    setInterval(() => {
      workbox.update().catch(() => {})
    }, UPDATE_INTERVAL_MINUTES * 60 * 1000)
  })
}

export default registerServiceWorker
