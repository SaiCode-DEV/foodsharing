import { post, remove, get } from './base'

export function sendBanana (recipientId, message) {
  return post(`/users/${recipientId}/bananas`, { message })
}

export function deleteBanana (recipientId, senderId) {
  return remove(`/users/${recipientId}/bananas/${senderId}`)
}

export function getBananaMetadata (userId) {
  return get(`/users/${userId}/bananas/meta`)
}

export function getReceivedBananas (recipientId) {
  return get(`/users/${recipientId}/bananas/received`)
}

export function getSentBananas (senderId) {
  return get(`/users/${senderId}/bananas/sent`)
}
