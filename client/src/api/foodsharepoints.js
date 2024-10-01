import { get, post } from './base'

export async function listFoodSharePoints (regionId) {
  return await get(`/regions/${regionId}/foodSharePoints`)
}

export async function addFoodSharePoint (regionId, data) {
  return post(`/regions/${regionId}/foodSharePoints`, data)
}
