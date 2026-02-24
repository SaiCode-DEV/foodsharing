import { HTTP_RESPONSE } from '@/consts'
import { patch, post, put } from './base'

export async function setProfilePhoto (uuid) {
  return await put('/users/current/photo', {
    uuid,
  })
}

export function requestEmailChange (userId, email, password) {
  return patch(`/users/${userId}/email`, {
    email,
    password,
  }, {
    skipErrorNotificationFor: [HTTP_RESPONSE.FORBIDDEN], // 403 = Wrong password
  })
}

export function requestPasswordChange (oldPassword, newPassword, totp = null) {
  const payload = { oldPassword, newPassword }
  if (totp) payload.totpCode = totp
  return patch('/users/current/password', payload, {
    skipErrorNotificationFor: [HTTP_RESPONSE.FORBIDDEN], // 403 = Wrong password / TOTP required
  })
}

export function get2FAdata () {
  return post('/users/current/2fa')
}

export function set2FA (password, code, enable, userId = null) {
  if (userId === null) userId = 'current'
  return patch(`/users/${userId}/2fa`, {
    password,
    code,
    enable,
  }, {
    skipErrorNotificationFor: [HTTP_RESPONSE.FORBIDDEN], // 403 = TOTP required
  })
}
