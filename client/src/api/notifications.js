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
  emailOnNewsletter = null,
  emailOnStoreManagerPickupReminder = null,
  bellOnMention = null,
}) {
  return patch('/notifications', { emailOnChatMessage, emailOnNewsletter, emailOnStoreManagerPickupReminder, bellOnMention })
}
