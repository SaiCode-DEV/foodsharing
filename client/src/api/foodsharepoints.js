import { get, patch, post, remove } from './base'

export async function listFoodSharePoints (regionId) {
  return await get(`/regions/${regionId}/food-share-points`)
}

export async function addFoodSharePoint (data) {
  return post(`/regions/${data.regionId}/food-share-points`, data)
}

export async function getFoodSharePoint (foodSharePointId) {
  return await get(`/food-share-points/${foodSharePointId}`)
}

export async function getFoodSharePointPermissions (foodSharePointId) {
  return await get(`/food-share-points/${foodSharePointId}/permissions`)
}

export async function updateFoodSharePoint (foodSharePointId, foodSharePointData) {
  return patch(`/food-share-points/${foodSharePointId}`, foodSharePointData)
}

export async function deleteFoodSharePoint (foodSharePointId) {
  return remove(`/food-share-points/${foodSharePointId}`)
}

export async function followFoodSharePoint (foodSharePointId, sendMails = false) {
  return await post(`/food-share-points/${foodSharePointId}/followers?sendMails=${sendMails}`)
}

export async function unfollowFoodSharePoint (foodSharePointId) {
  return await remove(`/food-share-points/${foodSharePointId}/followers`)
}

export async function acceptFoodSharePoint (foodSharePointId) {
  return await patch(`/food-share-points/${foodSharePointId}/status`)
}
