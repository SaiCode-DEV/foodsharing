import { get } from './base'

export async function getAchievementsFromRegion (regionId) {
  return await get(`/achievements/region/${regionId}`)
}
