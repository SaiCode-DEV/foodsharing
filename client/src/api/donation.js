import { get } from './base'

export async function getDonation () {
  return await get('/donation-goal')
}
