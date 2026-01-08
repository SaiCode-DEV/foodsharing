import { get } from './base'

export async function getPetitionData () {
  return await get('/petition').catch(error => {
    if (error.response && error.response.status === 503) {
      return null
    }
  })
}
