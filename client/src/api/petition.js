import { get } from './base'

export async function getPetitionData () {
  return await get('/petition-data')
}
