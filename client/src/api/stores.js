import { HTTP_RESPONSE } from '@/consts'
import { get, patch, post, remove } from './base'

export async function getStoreMetadata (version, hasChains) {
  return await get(`/stores/meta-data?version=${version}&hasChains=${+hasChains}`)
}

export async function getStoreMember (storeId) {
  return await get(`/stores/${storeId}/members`)
}
export async function getStoreInformation (storeId) {
  const result = await get(`/stores/${storeId}/details`)
  result.chainId = result.chain ? result.chain.id : null
  result.categoryId = result.category ? result.category.id : null
  return result
}

export async function updateStore (store) {
  await patch(`/stores/${store.id}/details`, store, { skipErrorNotificationFor: [HTTP_RESPONSE.BAD_REQUEST] })
}

function normalizeStoreWallPost (post) {
  post.createdAt = new Date(Date.parse(post.createdAt))
  post.body = post.text
  delete post.text
  return post
}

export async function getStoreWall (storeId) {
  const posts = (await get(`/stores/${storeId}/posts`))
  return posts.map(normalizeStoreWallPost)
}

export async function writeStorePost (storeId, text) {
  const newPost = (await post(`/stores/${storeId}/posts`, { text })).post
  return normalizeStoreWallPost(newPost)
}

export async function setStoreTeamStatus (storeId, status) {
  return patch(`/stores/${storeId}/details`, { teamStatus: status })
}

export async function deleteStorePost (storeId, postId) {
  return remove(`/stores/${storeId}/posts/${postId}`)
}

export function listStoresForUser (excludeInactive = false, userId) {
  return get(`/users/${userId}/stores?excludeInactive=${excludeInactive}`)
}

export function listStoresDetailsForUser (userId) {
  return get(`/users/${userId}/stores?format=location`)
}
export async function listStoreTeamMembershipRequests (storeId) {
  return get(`/stores/${storeId}/requests`)
}

export async function requestStoreTeamMembership (storeId, message = null) {
  return post(`/stores/${storeId}/requests`, { message })
}

export async function acceptStoreRequest (storeId, userId, moveToStandby) {
  return patch(`/stores/${storeId}/requests/${userId}?moveToStandby=${moveToStandby}`)
}

export async function declineStoreRequest (storeId, userId, message) {
  return remove(`/stores/${storeId}/requests/${userId}`, { message })
}

export async function promoteToStoreManager (storeId, userId) {
  return post(`/stores/${storeId}/managers/${userId}`)
}

export async function demoteAsStoreManager (storeId, userId, message) {
  return remove(`/stores/${storeId}/managers/${userId}`, { message })
}

export async function addStore (regionId, store, firstPost) {
  return post(`/regions/${regionId}/stores`, {
    store,
    firstPost,
  })
}

export async function removeStoreMember (storeId, userId, message) {
  return remove(`/stores/${storeId}/members/${userId}`, { message })
}

export async function moveMemberToStandbyTeam (storeId, userId, message) {
  return patch(`/stores/${storeId}/members/${userId}/standby`, { message })
}

export async function moveMemberToRegularTeam (storeId, userId) {
  return remove(`/stores/${storeId}/members/${userId}/standby`)
}

export async function getStoreLog (storeId, storeActionTypes, dateRange, offset = 0) {
  dateRange[0].setHours(0, 0, 0, 0)
  dateRange[1].setHours(24, 0, 0, 0)
  const [fromDate, toDate] = dateRange.map(date => date.toISOString())
  return get(`/stores/${storeId}/log/${fromDate}/${toDate}/actions/${storeActionTypes.join(',')}?offset=${offset}`)
}

export async function getStorePermissions (storeId) {
  return get(`/stores/${storeId}/permissions`)
}

export async function deleteStore (storeId) {
  return remove(`/stores/${storeId}`, {}, { skipErrorNotificationFor: [HTTP_RESPONSE.CONFLICT] })
}

export async function listStoreTeamInvitations (storeId) {
  return get(`/stores/${storeId}/invitations`)
}
export async function inviteStoreMember (storeId, userId) {
  return post(`/stores/${storeId}/invitations/${userId}`)
}
export async function withdrawStoreTeamInvitation (storeId, userId) {
  return remove(`/stores/${storeId}/invitations/${userId}`)
}
export async function acceptInvitation (storeId) {
  return patch(`/stores/${storeId}/invitations/current`)
}
export async function declineInvitation (storeId) {
  return remove(`/stores/${storeId}/invitations/current`)
}
