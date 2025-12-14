import { patch, get } from './base'

export async function setProfilePhoto (uuid) {
  return await patch('/user/photo', {
    uuid,
  })
}

export function requestEmailChange (userId, email, password) {
  return patch(`/user/${userId}/email`, {
    email,
    password,
  }, {
    skipErrorNotificationFor: [403], // 403 = Wrong password
  })
}

export function requestPasswordChange (oldPassword, newPassword) {
  return patch('/user/current/password', {
    oldPassword,
    newPassword,
  }, {
    skipErrorNotificationFor: [403], // 403 = Wrong password
  })
}

export function get2FAdata () {
  return get('/user/current/2fa/generate')
}

export function set2FA (password, code, enable, userId = null) {
  if (userId === null) userId = 'current'
  return patch(`/user/${userId}/2fa`, {
    password,
    code,
    enable,
  }, {
    skipErrorNotificationFor: [403], // 403 = TOTP required
  })
}
