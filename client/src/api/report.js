import { get, post, remove, patch } from './base'
import { HTTP_RESPONSE } from '@/consts'

export async function getReportsByRegion (regionId) {
  return await get(`regions/${regionId}/reports`)
}

export async function getReportsByUser (userId) {
  return await get(`users/${userId}/reports`)
}

export async function deleteReport (reportId) {
  await remove(`/reports/${reportId}`)
}

export async function updateReport (reportId, updateData) {
  return await patch(`/reports/${reportId}`, updateData, {
    skipErrorNotificationFor: [HTTP_RESPONSE.BAD_REQUEST],
  })
}

export function addReport (userId, reason, message, storeId) {
  return post(`/users/${userId}/reports`, {
    reason,
    message,
    storeId,
  })
}
