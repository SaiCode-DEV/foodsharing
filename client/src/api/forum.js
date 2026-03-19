import { HTTP_RESPONSE } from '@/consts'
import { get, post, patch, remove, put } from './base'

// *** FORUM MANAGEMENT *** //

export function getForumFollowing (regionId) {
  return get(`/regions/${regionId}/forum/subscriptions`)
}

export function setForumFollowing (regionId, isFollowing) {
  return put(`/regions/${regionId}/forum/subscriptions?isFollowing=${isFollowing}`)
}

// *** THREAD MANAGEMENT *** //

export function listThreads (regionId, subforumId, offset = 0) {
  return get(`/regions/${regionId}/forum/threads?subforumId=${subforumId}&offset=${offset}`)
}

export function getThread (threadId) {
  return get(`/forum/threads/${threadId}`)
}

export function createThread (regionId, subforumId, title, body, sendMail) {
  return post(`/regions/${regionId}/forum/threads?subforumId=${subforumId}`, {
    title,
    body,
    sendMail,
  })
}

export function setStickinessThread (threadId, stickiness) {
  return patch(`/forum/threads/${threadId}`, { stickiness })
}

export function activateThread (threadId) {
  return patch(`/forum/threads/${threadId}`, {
    isActive: true,
  })
}

export function setThreadStatus (threadId, status) {
  return patch(`/forum/threads/${threadId}`, {
    status,
  })
}

export function setTitle (threadId, title) {
  return patch(`/forum/threads/${threadId}`, { title })
}

export function deleteThread (threadId) {
  return remove(`/forum/threads/${threadId}`)
}

export function followThreadByEmail (threadId) {
  return post(`/forum/threads/${threadId}/follow/email`)
}

export function followThreadByBell (threadId) {
  return post(`/forum/threads/${threadId}/follow/bell`)
}

export function unfollowThreadByEmail (threadId) {
  return remove(`/forum/threads/${threadId}/follow/email`)
}

export function unfollowThreadByBell (threadId) {
  return remove(`/forum/threads/${threadId}/follow/bell`)
}

// *** POST MANAGEMENT *** //

export function createPost (threadId, body) {
  return post(`/forum/threads/${threadId}/posts`, {
    body,
  })
}

export function deletePost (postId) {
  return remove(`/forum/posts/${postId}`)
}

export function editPost (postId, body) {
  return patch(`/forum/posts/${postId}`, { body }, { skipErrorNotificationFor: [HTTP_RESPONSE.CONFLICT, HTTP_RESPONSE.BAD_REQUEST] })
}

export function hidePost (postId, reason) {
  return patch(`/forum/posts/${postId}/hidden`, { reason })
}

export function restorePost (postId) {
  return remove(`/forum/posts/${postId}/hidden`)
}

// *** REACTION MANAGEMENT *** //

export function addReaction (postId, key) {
  return post(`/forum/posts/${postId}/reactions/${key}`)
}

export function removeReaction (postId, key) {
  return remove(`/forum/posts/${postId}/reactions/${key}`)
}
