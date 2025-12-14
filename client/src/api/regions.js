import { get, patch, post, remove } from './base'

export function joinRegion (regionId) {
  return post(`/region/${regionId}/join`)
}

export function leaveRegion (regionId) {
  return post(`/region/${regionId}/leave`)
}

export function setRegionOptions (regionId, enableReportButton, enableMediationButton, regionPickupRuleActive, regionPickupRuleTimespan, regionPickupRuleLimit, regionPickupRuleLimitDay, regionPickupRuleInactive, selectedReportReasonOptions, enableReportReasonOther, enableAddressChangeNotification) {
  return post(`/region/${regionId}/options`, {
    enableReportButton,
    enableMediationButton,
    regionPickupRuleActive,
    regionPickupRuleTimespan,
    regionPickupRuleLimit,
    regionPickupRuleLimitDay,
    regionPickupRuleInactive,
    selectedReportReasonOptions,
    enableReportReasonOther,
    enableAddressChangeNotification,
  })
}

export function getRegionOptions (regionId) {
  return get(`/region/${regionId}/options`)
}

export function getRegionOptionPermissions (regionId) {
  return get(`/region/${regionId}/options/permissions`)
}

export function setRegionPin (regionId, { lat, lon, desc, status }) {
  return post(`/region/${regionId}/pin`, { lat, lon, desc, status })
}

export function listRegionChildren (regionId, includeWorkingGroups) {
  return get(`/region/${regionId}/children${includeWorkingGroups ? '?includeWorkingGroups' : ''}`)
}

export function listRegionMembers (regionId) {
  return get(`/region/${regionId}/members`)
}

export function listRegionStores (regionId) {
  return get(`/region/${regionId}/stores`)
}

export function removeMember (regionId, memberId) {
  return remove(`/region/${regionId}/members/${memberId}`)
}

export function removeAdminOrAmbassador (regionId, memberId) {
  return remove(`/region/${regionId}/members/${memberId}/admin`)
}

export function setAdminOrAmbassador (regionId, memberId) {
  return post(`/region/${regionId}/members/${memberId}/admin`)
}

export function getRegionData (regionId) {
  return get(`/region/${regionId}`)
}

export function patchRegion (region) {
  return patch(`/region/${region.id}`, region)
}

export async function createRegion (region) {
  return (await post('/region', region)).regionId
}

export async function getRegionMemberPermissions (regionId) {
  return await get(`/region/${regionId}/members/permissions`)
}

export async function getPublicRegionData (regionId) {
  return await get(`/region/${regionId}/public`)
}
export async function getRegionMenu (regionId) {
  return await get(`/region/${regionId}/menu`, { disableLoginRedirect: true })
}
export async function getInaccessibleRegionRedirects (regionId) {
  return await get(`/region/${regionId}/redirects`, { disableLoginRedirect: true })
}
