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

export async function getFoodSharePointPermissions (foodSharePointId) {
  return await get(`/foodSharePoints/${foodSharePointId}/permissions`)
}

export async function updateFoodSharePoint (foodSharePointId, foodSharePointData) {
  return patch(`/foodSharePoints/${foodSharePointId}`, foodSharePointData)
}

export async function deleteFoodSharePoint (foodSharePointId) {
  return remove(`/foodSharePoints/${foodSharePointId}`)
}

export async function followFoodSharePoint (foodSharePointId, sendMails = false) {
  return await post(`/foodSharePoints/${foodSharePointId}/follow?sendMails=${sendMails}`)
}

export async function unfollowFoodSharePoint (foodSharePointId) {
  return await remove(`/foodSharePoints/${foodSharePointId}/follow`)
}

export async function acceptFoodSharePoint (foodSharePointId) {
  return await post(`/foodSharePoints/${foodSharePointId}/accept`)
}
