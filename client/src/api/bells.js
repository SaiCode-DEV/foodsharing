import { get, patch, remove } from './base'

export async function getBellList (limit = 20, offset) {
  let path = `/bells?limit=${limit}`
  if (offset) path += '&offset=' + offset
  return await get(path)
}

export async function deleteBells (ids) {
  return await remove('/bells', { ids })
}

/**
 * Marks one or more bells as unread/read. Ignores IDs that do not exist (bells
 * that are either deleted or already in the desired state).
 *
 * @param {number[]} ids
 * @param {boolean} isRead
 * @returns {Promise<void>}
 */
export async function setReadStatus (ids, isRead) {
  return (await patch(`/bells/readStatus?read=${isRead ? 1 : 0}`, {
    ids,
  }, { skipErrorNotificationFor: [404] }))
}
