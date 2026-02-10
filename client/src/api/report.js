import { get, post, remove } from './base'

export async function getReportsByRegion (regionId) {
  return await get(`regions/${regionId}/reports`)
}

export async function getReportsByUser (userId) {
  return await get(`users/${userId}/reports`)
}

export async function deleteReport (reportId) {
  await remove(`/reports/${reportId}`)
}

export function addReport (userId, reason, message, storeId) {
  return post(`/users/${userId}/reports`, {
    reason,
    message,
    storeId,
  })
}
