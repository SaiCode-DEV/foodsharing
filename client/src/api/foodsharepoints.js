import { get, patch, post } from './base'

export async function listFoodSharePoints (regionId) {
  return await get(`/regions/${regionId}/foodSharePoints`)
}

export async function addFoodSharePoint (regionId, data) {
  return post(`/regions/${regionId}/foodSharePoints`, data)
}

export async function getFoodSharePoint (foodSharePointId) {
  return await get(`/foodSharePoints/${foodSharePointId}`)
}

export async function updateFoodSharePoint (foodSharePointId) {
  return await patch(`/foodSharePoints/${foodSharePointId}`)
}
