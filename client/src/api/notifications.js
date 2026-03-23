import { HTTP_RESPONSE } from '@/consts'
import { get, patch } from './base'

export function getFoodSharePointsNotification () {
  return get('/notifications/food-share-points')
}

export function listRegionsWithoutWorkingGroups () {
  return get('/notifications/regions')
}

export function getThreadsNotification () {
  return get('/notifications/threads')
}

export function getGeneralNotificationSettings () {
  return get('/notifications')
}

export function getNewsletterNotificationSettings () {
  return get('/notifications/newsletter', {
    skipErrorNotificationFor: [HTTP_RESPONSE.SERVICE_UNAVAILABLE], // 503 = Newsletter-Server not available
  })
}

export function listWorkingGroups () {
  return get('/notifications/regions?groups=true')
}

export function updateRegionsAndWorkgroupsNotification (regions) {
  return patch('/notifications/regions', { notifications: regions })
}

export function setThreadsNotification (threads) {
  return patch('/notifications/threads', { notifications: threads })
}

export function setFoodSharePointsNotification (foodSharePoints) {
  return patch('/notifications/food-share-points', { notifications: foodSharePoints })
}

export function setGeneralNotificationSettings ({
  emailOnChatMessage = null,
  emailOnStoreManagerPickupReminder = null,
  bellOnMention = null,
}) {
  return patch('/notifications', { emailOnChatMessage, emailOnStoreManagerPickupReminder, bellOnMention })
}

export function setNewsletterNotificationSettings (isNewsletterSubscribed) {
  return patch('/notifications/newsletter', { isNewsletterSubscribed })
}
