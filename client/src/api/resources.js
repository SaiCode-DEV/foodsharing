import { get, patch, post, remove } from './base'

export async function getResourcesForRegion (regionId) {
  return await get(`/regions/${regionId}/resources`)
}

export async function getOwnResources () {
  return await get('/resources/own')
}

export async function getResourceCategories () {
  return await get('/resources/categories')
}

export async function postResource (resource) {
  return await post('/resources', resource)
}

export async function postCommonsResource (resource) {
  return await post('/resources/commons', resource)
}

export async function patchResource (resourceId, resource) {
  return await patch(`/resources/${resourceId}`, resource)
}

export async function deleteResource (resourceId) {
  return await remove(`/resources/${resourceId}`)
}

export async function favoriteResource (resourceId) {
  return await post(`/resources/${resourceId}/favorite`)
}

export async function unfavoriteResource (resourceId) {
  return await remove(`/resources/${resourceId}/favorite`)
}

export async function getResourcePermissions (regionId) {
  return await get(`/regions/${regionId}/resources/permissions`)
}
