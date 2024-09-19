import { put, remove, get } from './base'

export function sendBanana (recipientId, message) {
  return put(`/user/${recipientId}/banana`, { message: message })
}

export function deleteBanana (recipientId, senderId) {
  return remove(`/user/${recipientId}/banana/${senderId}`)
}

export function getBananaMetadata (userId) {
  return get(`/user/${userId}/banana/meta`)
}

export function getReceivedBananas (recipientId) {
  return get(`/user/${recipientId}/banana/received`)
}

export function getSentBananas (senderId) {
  return get(`/user/${senderId}/banana/sent`)
}
