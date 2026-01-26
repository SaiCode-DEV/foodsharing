import { get } from './base'

export async function getRegionGenderData (regionId, homeRegion) {
  return get(`/regions/${regionId}/statistics/gender?onlyHomeRegion=${homeRegion ? 'true' : 'false'}`)
}

export async function getRegionAgeBandData (regionId, homeRegion) {
  return get(`/regions/${regionId}/statistics/age-band?onlyHomeRegion=${homeRegion ? 'true' : 'false'}`)
}

export async function getRegionPickupStatisticsData (regionId) {
  return get(`/regions/${regionId}/statistics/pickups`)
}

export async function getOverallStatistics () {
  return get('/statistics')
}
