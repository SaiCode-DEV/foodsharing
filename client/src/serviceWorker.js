import { urls } from '@/helper/urls'
import { subscribeForPushNotifications } from '@/pushNotifications'

self.addEventListener('push', async function (event) {
  const roundCorners = (() => {
    const size = 32
    const radius = 6
    const canvas = new OffscreenCanvas(size, size)
    const ctx = canvas.getContext('2d')

    // Prepare clip path for rounded corners:
    ctx.beginPath()
    ctx.moveTo(radius, 0)
    ctx.lineTo(size - radius, 0)
    ctx.quadraticCurveTo(size, 0, size, radius)
    ctx.lineTo(size, size - radius)
    ctx.quadraticCurveTo(size, size, size - radius, size)
    ctx.lineTo(radius, size)
    ctx.quadraticCurveTo(0, size, 0, size - radius)
    ctx.lineTo(0, radius)
    ctx.quadraticCurveTo(0, 0, radius, 0)
    ctx.closePath()
    ctx.clip()
    ctx.save()

    async function fetchImageBitmap (imageUrl) {
      const response = await fetch(imageUrl)
      const blob = await response.blob()
      const imageBitmap = await createImageBitmap(blob)
      return imageBitmap
    }

    return async function (imageUrl) {
      ctx.restore()
      const image = await fetchImageBitmap(imageUrl)
      ctx.drawImage(image, 0, 0, size, size)
      const blob = await canvas.toBlob()
      const fileReader = new FileReader()
      const loaded = new Promise(resolve => fileReader.addEventListener('load', resolve))
      fileReader.readAsDataURL(blob)
      await loaded
      return fileReader.result
    }
  })()

  if (!self.Notification || self.Notification.permission !== 'granted') {
    return
  }

  if (!event.data) {
    return
  }

  const data = event.data.json()
  if (data.options.icon) {
    data.options.icon = await roundCorners(data.options.icon)
  }
  event.waitUntil(self.registration.showNotification(data.title, data.options))
})

self.addEventListener('notificationclick', function (event) {
  event.waitUntil((async () => {
    const notifications = await self.registration.getNotifications()
    if (event.notification.data.action) {
      const { page, params } = event.notification.data.action
      const url = urls[page](...params)
      event.notification.close() // for android users you have to explicitly close the notification
      const notificationOfSameKind = notifications.filter(notification => notification.data.action.page === page)
      switch (page) {
        case 'conversations': // close all notifications of the same thread
          notificationOfSameKind
            .filter(notification => JSON.stringify(notification.data.action.params) === JSON.stringify(params))
            .forEach(notification => notification.close())
          break
        default: // close notification of the same kind
          notificationOfSameKind.forEach(notification => notification.close())
      }
      // open a new window or focus the foodsharing tab of same kind
      return await self.clients.matchAll({ type: 'window' }).then((clients) => {
        for (const client of clients) {
          const clientUrl = new URL(client.url)
          const notificationUrl = new URL(clientUrl.host + url)
          if (clientUrl.searchParams.get('page') === notificationUrl.searchParams.get('page')) {
            return client.focus().then(() => client.navigate(url))
          }
        }
        return self.clients.openWindow(url)
      })
    }
  })())
})

// Time to time, browsers decide to reset their push subscription data. Then all subscriptions for this browser become invalid, and we need to register a new one.
self.addEventListener('pushsubscriptionchange', function (event) {
  event.waitUntil(subscribeForPushNotifications(event.oldSubscription.options))
  // we don't need to care about the old subscription on the server, it's going to get removed automatically as soon as the server realizes it's invalid
})

// Ensure new workers to replace old ones...
// https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerGlobalScope/skipWaiting

self.addEventListener('install', event => {
  event.waitUntil(self.skipWaiting())
})

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim())
})
