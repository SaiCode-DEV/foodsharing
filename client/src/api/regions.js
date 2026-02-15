import { get, patch, post, put, remove } from './base'

export function joinRegion (regionId) {
  return put(`/regions/${regionId}/users/current`)
}

export function leaveRegion (regionId) {
  return remove(`/regions/${regionId}/users/current`)
}

export function setRegionOptions (regionId, isReportButtonEnabled, isMediationButtonEnabled, isRegionPickupRuleActive, regionPickupRuleTimespanDays, regionPickupRuleLimitNumber, regionPickupRuleLimitDayNumber, regionPickupRuleInactiveHours, selectedReportReasonOptions, isReportReasonOtherEnabled, isAddressChangeNotificationEnabled) {
  return patch(`/regions/${regionId}/options`, {
    isReportButtonEnabled,
    isMediationButtonEnabled,
    isRegionPickupRuleActive,
    regionPickupRuleTimespanDays,
    regionPickupRuleLimitNumber,
    regionPickupRuleLimitDayNumber,
    regionPickupRuleInactiveHours,
    selectedReportReasonOptions,
    isReportReasonOtherEnabled,
    isAddressChangeNotificationEnabled,
  })
}

export function getRegionOptions (regionId) {
  return get(`/regions/${regionId}/options`)
}

export function getRegionOptionPermissions (regionId) {
  return get(`/regions/${regionId}/options/permissions`)
}

export function setPublicRegionData (regionId, { location, description, showPin }) {
  return patch(`/regions/${regionId}/public`, { location, description, showPin })
}

export function listRegionChildren (regionId, includeWorkingGroups) {
  return get(`/regions/${regionId}/children${includeWorkingGroups ? '?includeWorkingGroups=1' : ''}`)
}

export function listRegionMembers (regionId) {
  return get(`/regions/${regionId}/users`)
}

export async function listRegionStores (regionId) {
  return get(`/regions/${regionId}/stores`)
}

export function removeMember (regionId, userId) {
  return remove(`/regions/${regionId}/users/${userId}`)
}

export function removeAdminOrAmbassador (regionId, userId) {
  return remove(`/regions/${regionId}/users/${userId}/admin`)
}

export function setAdminOrAmbassador (regionId, userId) {
  return post(`/regions/${regionId}/users/${userId}/admin`)
}

export function getRegionData (regionId) {
  return get(`/regions/${regionId}`)
}

export function patchRegion (region) {
  return patch(`/regions/${region.id}`, region)
}

export async function createRegion (region) {
  return (await post('/regions', region)).regionId
}

export async function getRegionMemberPermissions (regionId) {
  return await get(`/regions/${regionId}/users/permissions`)
}

export async function getPublicRegionData (regionId) {
  return await get(`/regions/${regionId}/public`)
}
export async function getRegionMenu (regionId) {
  return await get(`/regions/${regionId}/menu`, { disableLoginRedirect: true })
}
export async function getInaccessibleRegionRedirects (regionId) {
  return await get(`/regions/${regionId}/redirects`, { disableLoginRedirect: true })
}
