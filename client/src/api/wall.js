import { get, post, remove } from './base'

export function getWallPosts (target, targetId, limit, offset = 0) {
  return get(`/wall/${target}/${targetId}?limit=${limit}&offset=${offset}`)
}

export function addPost (target, targetId, body, pictures) {
  return post(`/wall/${target}/${targetId}`, { body, pictures })
}

export function deletePost (target, targetId, postId) {
  return remove(`/wall/${target}/${targetId}/${postId}`)
}

export function addReaction (target, targetId, postId, key) {
  return post(`/wall/${target}/${targetId}/${postId}/reaction/${key}`)
}

export function removeReaction (target, targetId, postId, key) {
  return remove(`/wall/${target}/${targetId}/${postId}/reaction/${key}`)
}
