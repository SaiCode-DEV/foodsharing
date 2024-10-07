import { get, patch, post, remove } from './base'

export async function listFoodSharePoints (regionId) {
  return await get(`/regions/${regionId}/foodSharePoints`)
}

export async function addFoodSharePoint (data) {
  return post(`/regions/${data.regionId}/foodSharePoints`, data)
}

export async function getFoodSharePoint (foodSharePointId) {
  return await get(`/foodSharePoints/${foodSharePointId}`)
}

export async function updateFoodSharePoint (foodSharePointId, foodSharePointData) {
  return patch(`/foodSharePoints/${foodSharePointId}`, foodSharePointData)
}

export async function deleteFoodSharePoint (foodSharePointId) {
  return remove(`/foodSharePoints/${foodSharePointId}`)
}
