import { get, post, remove } from './base'

export async function sendBuddyRequest (userId) {
  return await post(`/users/${userId}/buddies`)
}

export async function removeBuddy (userId) {
  return await remove(`/users/${userId}/buddies`)
}

export async function getBuddies () {
  return await get('/users/current/buddies')
}
