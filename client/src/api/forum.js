import { get, post, put, patch, remove } from './base'

export function listThreads (groupId, subforumId, offset = 0) {
  return get(`/forum/${groupId}/${subforumId}?offset=${offset}`)
}

export function getThread (threadId) {
  return get(`/forum/thread/${threadId}`)
}
export function deleteThread (threadId) {
  return remove(`/forum/thread/${threadId}`)
}

export function followThreadByEmail (threadId) {
  return post(`/forum/thread/${threadId}/follow/email`)
}

export function followThreadByBell (threadId) {
  return post(`/forum/thread/${threadId}/follow/bell`)
}

export function unfollowThreadByEmail (threadId) {
  return remove(`/forum/thread/${threadId}/follow/email`)
}

export function unfollowThreadByBell (threadId) {
  return remove(`/forum/thread/${threadId}/follow/bell`)
}

export function setStickinessThread (threadId, stickiness) {
  return patch(`/forum/thread/${threadId}`, { stickiness })
}

export function activateThread (threadId) {
  return patch(`/forum/thread/${threadId}`, {
    isActive: true,
  })
}

export function setThreadStatus (threadId, status) {
  return patch(`/forum/thread/${threadId}`, {
    status: status,
  })
}

export function createPost (threadId, body) {
  return post(`/forum/thread/${threadId}/posts`, {
    body: body,
  })
}

export function updatePost (postId, body) {
  return put('/forum/post', {
    body: body,
  })
}

export function deletePost (postId) {
  return remove(`/forum/post/${postId}`)
}

export function hidePost (postId, reason) {
  return patch(`/forum/post/${postId}/hide`, { reason })
}

export function restorePost (postId) {
  return remove(`/forum/post/${postId}/hide`)
}

export function addReaction (postId, key) {
  return post(`/forum/post/${postId}/reaction/${key}`)
}

export function removeReaction (postId, key) {
  return remove(`/forum/post/${postId}/reaction/${key}`)
}

export function createThread (forumId, forumSubId, title, body, sendMail) {
  return post(`/forum/${forumId}/${forumSubId}`, {
    title: title,
    body: body,
    sendMail: sendMail,
  },
  )
}

export function setTitle (threadId, title) {
  return patch(`/forum/thread/${threadId}`, { title })
}
