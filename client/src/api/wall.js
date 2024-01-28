import { get, post, remove } from './base'

export function getWallPosts (target, targetId) {
  return get(`/wall/${target}/${targetId}`)
}

export function addPost (target, targetId, body, pictures) {
  return post(`/wall/${target}/${targetId}`, { body, pictures })
}

export function deletePost (target, targetId, postId) {
  return remove(`/wall/${target}/${targetId}/${postId}`)
}
