import { get, patch, post, remove } from './base'

export async function getAchievementsFromRegion (regionId) {
  return await get(`/achievements/region/${regionId}`)
}

export async function getAwardedUsersForAchievement (achievementId) {
  return await get(`/achievements/${achievementId}/users`)
}

export async function awardAchievement (userId, achievementId, options) {
  return await post(`/achievements/${achievementId}/users/${userId}`, options)
}

export async function editAchievement (userId, achievementId, options) {
  return await patch(`/achievements/${achievementId}/users/${userId}`, options)
}

export async function revokeAchievement (userId, achievementId) {
  return await remove(`/achievements/${achievementId}/users/${userId}`)
}

export async function addAchievement (achievement) {
  return await post('/achievements', achievement)
}

export async function deleteAchievement (achievementId) {
  return await remove(`/achievements/${achievementId}`)
}

export async function patchAchievement (achievement) {
  achievement = Object.assign({}, achievement)
  const id = achievement.id
  delete achievement.id
  return await patch(`/achievements/${id}`, achievement)
}
