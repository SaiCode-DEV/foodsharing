import runtime from 'serviceworker-webpack-plugin/lib/runtime'

function registerServiceWorker () {
  document.addEventListener('DOMContentLoaded', () => {
    if (!('serviceWorker' in navigator)) {
      console.warn('Service workers are not supported by this browser')
      return
    }
    try {
      runtime.register()
    } catch (error) {
      console.warn('Service worker registration failed:', error)
    }
  })
}

export default registerServiceWorker
