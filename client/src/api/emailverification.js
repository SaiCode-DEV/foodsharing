import { post } from './base'

export async function requestVerificationEmail (address) {
  return await post('/emailverification', { address })
}
