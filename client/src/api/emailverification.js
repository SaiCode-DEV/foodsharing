import { put } from './base'

export async function requestVerificationEmail (address) {
  return await put('/email-verification', { address })
}
