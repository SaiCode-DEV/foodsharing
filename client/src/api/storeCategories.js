import { get, patch, post, remove } from './base'

export async function getStoreCategories () {
  return await get('/storecategories')
}

export async function addStoreCategory (name) {
  return await post('/storecategories', { name })
}

export async function editStoreCategory (id, name) {
  return await patch(`/storecategories/${id}`, { name })
}

export async function removeStoreCategory (id) {
  return await remove(`/storecategories/${id}`)
}
