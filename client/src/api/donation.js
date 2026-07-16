import { get, patch } from './base'

export async function getDonationData () {
  return await get('/donation-data')
}

export async function updateDonationData (data) {
  return await patch('/donation-data', data)
}

export async function getDonationProjects () {
  return await get('/donation-projects')
}
