import { get, patch, remove } from './base'

export async function acceptApplication (groupId, userId) {
  return await patch(`/groups/${groupId}/applications/${userId}`)
}

export async function declineApplication (groupId, userId) {
  return await remove(`/groups/${groupId}/applications/${userId}`)
}

export async function getApplications (groupId) {
  return await get(`/groups/${groupId}/applications`)
}
