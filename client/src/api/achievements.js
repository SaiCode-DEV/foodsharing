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

export async function editAchievement (awardedAchievementId, options) {
  return await patch(`/achievements/awarded/${awardedAchievementId}`, options)
}

export async function revokeAchievement (awardedAchievementId) {
  return await remove(`/achievements/awarded/${awardedAchievementId}`)
}

export async function addAchievement (achievement) {
  return (await post('/achievements', achievement)).id
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
