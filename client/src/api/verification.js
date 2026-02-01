import { post, get, remove } from './base'

export async function verifyUser (userId, message) {
  return post(`/users/${userId}/verifications`, { message })
}

export async function deverifyUser (userId) {
  return remove(`/users/${userId}/verifications`)
}

export async function getVerificationHistory (userId) {
  return await get(`/users/${userId}/verifications`)
}

export async function getPassHistory (userId) {
  return await get(`/users/${userId}/pass-history`)
}

export async function getPassportAsUser () {
  return await get('/users/current/passport', { responseType: 'blob' })
}

export async function createPassportAsAmbassador (regionId, userIds, createPdf, renew, informUser, usePaperSizeDinA4) {
  const options = createPdf ? { responseType: 'blob' } : {}
  return await post(`/regions/${regionId}/passports`, { userIds, createPdf, renew, informUser, usePaperSizeDinA4 }, options)
}

export const walletUrls = Object.freeze({
  GOOGLE: '/api/users/current/wallets/google',
  APPLE: '/api/users/current/wallets/apple',
})
