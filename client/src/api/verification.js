import { post, get, patch, remove } from './base'

export async function verifyUser (userId) {
  return patch(`/user/${userId}/verification`)
}

export async function deverifyUser (userId) {
  return remove(`/user/${userId}/verification`)
}

export async function getVerificationHistory (userId) {
  return await get(`/user/${userId}/verificationhistory`)
}

export async function getPassHistory (userId) {
  return await get(`/user/${userId}/passhistory`)
}

export async function createPassportAsUser () {
  return await post('/user/current/passport', {}, { responseType: 'blob' })
}

export async function createPassportAsAmbassador (regionId, userIds) {
  return await post(`/region/${regionId}/passport`, { userIds: userIds }, { responseType: 'blob' })
}
