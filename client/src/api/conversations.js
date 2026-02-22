import { get, patch, post, put } from './base'
import { generateQueryString } from '../utils'

export function getConversationList (limit = '', offset = '') {
  const queryString = generateQueryString({ limit, offset })
  return get(`/conversations${queryString}`)
}

export function getConversation (conversationId, markAsRead = false) {
  const queryString = generateQueryString({ markAsRead: markAsRead ? 1 : 0 })
  return get(`/conversations/${conversationId}${queryString}`)
}

export function getConversationIdForConversationWithUser (userId) {
  return post('/conversations/lookup', {
    ids: [userId],
  })
}

export function getMessages (conversationId, olderThanId = '', limit = '') {
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
    name: newName || null,
  })
}

export async function createConversation (userIds) {
  const id = (await post('/conversations/lookup', {
    ids: userIds,
  })).id
  return getConversation(id)
}

export function setReadStatus (conversationId, read) {
  return put(`/conversations/${conversationId}/read-status?isRead=${read ? 1 : 0}`)
}
