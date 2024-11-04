import { get, patch } from './base'

export function getFoodSharePointsNotification () {
  return get('/notifications/foodsharepoints')
}

export function listRegionsWithoutWorkingGroups () {
  return get('/notifications/regions')
}

export function getThreadsNotification () {
  return get('/notifications/forum')
}

export function getUserNotification () {
  return get('/notifications/user')
}

export function listWorkingGroups () {
  return get('/notifications/groups')
}

export function getPickupReminderNotification () {
  return get('/notifications/pickupreminder')
}

export function getMentionNotification () {
  return get('/notifications/mention')
}

export function updateRegionsAndWorkgroupsNotification (regions) {
  return patch('/notifications/regions', regions)
}

export function setThreadsNotification (threads) {
  return patch('/notifications/forum', threads)
}

export function setFoodSharePointsNotification (foodSharePoints) {
  return patch('/notifications/foodsharepoints', foodSharePoints)
}

export function setPickupReminderNotification (sendMail) {
  return patch('/notifications/pickupreminder', { sendMail })
}

export function setMentionNotification (mention) {
  return patch('/notifications/mention', { mention })
}

export function setUserNotification (newsletter, chat) {
  return patch('/notifications/user', { newsletter, chat })
}
