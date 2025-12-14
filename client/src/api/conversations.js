import { get, patch, post, remove } from './base'
import { generateQueryString } from '../utils'

export function getConversationList (limit = '', offset = '') {
  const queryString = generateQueryString({ limit, offset })
  return get(`/conversations${queryString}`)
}

export function getConversation (conversationId) {
  return get(`/conversations/${conversationId}`)
}

export function getConversationIdForConversationWithUser (userId) {
  return get(`/user/${userId}/conversation`)
}

export function getMessages (conversationId, olderThanId, limit = '') {
  const queryString = generateQueryString({ olderThanId, limit })
  return get(`/conversations/${conversationId}/messages${queryString}`)
}

export function sendMessage (conversationId, body) {
  return post(`/conversations/${conversationId}/messages`, {
    body,
  })
}

export function renameConversation (conversationId, newName) {
  return patch(`/conversations/${conversationId}`, {
    name: newName,
  })
}

export function removeUserFromConversation (conversationId, userId) {
  return remove(`/conversations/${conversationId}/members/${userId}`)
}

export function createConversation (userIds) {
  return post('/conversations', {
    members: userIds,
  })
}

export function setReadStatus (conversationId, read) {
  return post(`/conversations/${conversationId}/readStatus?read=${read ? 1 : 0}`)
}
