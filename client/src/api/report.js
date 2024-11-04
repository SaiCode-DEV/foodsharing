import { get, post, remove } from './base'

export async function getReportsByRegion (regionId) {
  return await get(`/report/region/${regionId}`)
}

export async function getReportsByUser (userId) {
  return await get(`/report/user/${userId}`)
}

export async function deleteReport (reportId) {
  await remove(`/report/${reportId}`)
}

export function addReport (reportedId, reporterId, reasonId, reason, message, storeId) {
  return post('/report', {
    reportedId: reportedId,
    reporterId: reporterId,
    reasonId: reasonId,
    reason: reason,
    message: message,
    storeId: storeId,
  })
}
