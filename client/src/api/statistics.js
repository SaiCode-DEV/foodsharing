import { get } from './base'

export async function getRegionGenderData (regionId, homeRegion) {
  return get(`/statistics/regions/${regionId}/gender?homeRegion=${homeRegion ? 'true' : 'false'}`)
}

export async function getRegionAgeBandData (regionId, homeRegion) {
  return get(`/statistics/regions/${regionId}/age-band?homeRegion=${homeRegion ? 'true' : 'false'}`)
}

export async function getRegionPickupStatisticsData (regionId) {
  return get(`/statistics/regions/${regionId}/pickups`)
}

export async function getOverallStatistics () {
  return get('/statistics')
}
