import { get, patch, remove } from './base'

export async function getBellList (limit = 20, offset) {
  let path = `/bells?limit=${limit}`
  if (offset) path += '&offset=' + offset
  return await get(path)
}

export function deleteBells (ids) {
  return remove('/bells', { ids })
}

/**
 * Returns the number of bells that were successfully marked as read.
 */
export async function markBellsAsRead (ids) {
  return (await patch('/bells', {
    ids: ids,
  })).marked
}
