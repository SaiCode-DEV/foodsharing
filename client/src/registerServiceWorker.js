import { Workbox } from 'workbox-window'

/**
 * Reset all service workers for the current origin
 * @returns {Promise<boolean>} True if service workers were found and reset, false if none were found
 */
async function resetServiceWorker () {
  if (!('serviceWorker' in navigator)) {
    console.warn('[SW] Service workers are not supported by this browser')
    return false
  }

  try {
    // Get all service worker registrations for this origin
    const registrations = await navigator.serviceWorker.getRegistrations()

    if (registrations.length === 0) {
      console.log('[SW] No service workers to reset')
      return false
    }

    console.log(`[SW] Found ${registrations.length} service worker(s) to reset`)

    // Unregister all service workers
    const unregisterPromises = registrations.map(registration => {
      console.log('[SW] Unregistering service worker:', registration.scope)
      return registration.unregister()
    })

    await Promise.all(unregisterPromises)

    // Clear all caches
    if ('caches' in window) {
      const cacheKeys = await caches.keys()
      await Promise.all(
        cacheKeys.map(cacheName => {
          console.log('[SW] Deleting cache:', cacheName)
          return caches.delete(cacheName)
        }),
      )
    }

    console.log('[SW] All service workers reset successfully')
    return true
  } catch (error) {
    console.error('[SW] Error resetting service workers:', error)
    throw error
  }
}

/**
 * Register the service worker using Workbox
 */
function registerServiceWorker () {
  if (!('serviceWorker' in navigator)) {
    console.warn('Service workers are not supported by this browser')
    return
  }

  // Increment SW_VERSION to force clients to fetch a fresh sw.js (cache busting)
  const workbox = new Workbox(`/sw.js?v=${new Date().getTime()}`)

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
}

function scheduleSWRegistration () {
  document.addEventListener('DOMContentLoaded', () => {
    // Delay registration to avoid impacting initial load performance
    setTimeout(() => {
      registerServiceWorker()
    }, 3000) // 3 seconds after DOMContentLoaded
  })
}

export { scheduleSWRegistration, registerServiceWorker, resetServiceWorker }
