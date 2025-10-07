import { get, post, remove } from './base'

export function getWallPosts (target, targetId, limit, offset = 0) {
  // We want to load the wall posts early on in stores, *before* we know if the
  // user is allowed to see them (jumpers aren't). Hence, the API may respond
  // with 403 errors that we need to ignore here. See
  // https://beta.foodsharing.de/region?bid=741&sub=forum&tid=301732&pid=1788926
  // and my Dominik's reply to this for reference.
  return get(`/wall/${target}/${targetId}?limit=${limit}&offset=${offset}`, { skipErrorNotificationFor: [403] })
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
