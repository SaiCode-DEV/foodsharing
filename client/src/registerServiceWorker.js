import { Workbox } from 'workbox-window'

function registerServiceWorker () {
  document.addEventListener('DOMContentLoaded', () => {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service workers are not supported by this browser')
      return
    }
    const workbox = new Workbox('/sw.js')

    workbox.addEventListener('installed', event => {
      if (event.isUpdate) {
        console.log('New content is available; please refresh.')
      }
    })

    workbox.addEventListener('ready', () => {
      console.log('Service Worker is ready.')
    })

    workbox.register().catch(error => {
      console.warn('Service worker registration failed:', error)
    })
  })
}

export default registerServiceWorker
