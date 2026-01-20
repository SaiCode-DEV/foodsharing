import { get, patch, post, remove } from './base'

export async function getCategories (type) {
  return await get(`/categories/${type}`)
}

export async function addCategory (type, name, subType = null) {
  return await post(`/categories/${type}`, { name, subType })
}

export async function editCategory (type, id, name, subType = null) {
  return await patch(`/categories/${type}/${id}`, { name, subType })
}

export async function removeCategory (type, id) {
  return await remove(`/categories/${type}/${id}`)
}

export async function mergeCategories (type, sourceId, targetId) {
  return await post(`/categories/${type}/${sourceId}/merges/${targetId}`)
}
