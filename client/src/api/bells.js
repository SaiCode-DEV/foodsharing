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
 * Marks one or more bells as unread/read.
 *
 * @param {number[]} ids
 * @param {boolean} isRead
 * @returns {Promise<number>} 1 (read) or 0 (unread)
 */
export async function setReadStatus (ids, isRead) {
  return (await patch(`/bells/readStatus?read=${isRead ? 1 : 0}`, {
    ids: ids,
  })).seen
}
