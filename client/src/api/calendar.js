import { get, put, remove } from './base'

export async function getApiToken () {
  return await get('/calendar/token', { skipErrorNotificationFor: [404] })
    .then(response => response.token)
    .catch(error => {
      if (error?.response?.status === 404) {
        return null
      }
      throw error
    })
}

export async function createApiToken () {
  return await put('/calendar/token')
}

export async function removeApiToken () {
  return await remove('/calendar/token')
}
