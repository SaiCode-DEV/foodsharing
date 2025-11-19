import { patch, get } from './base'

export async function setProfilePhoto (uuid) {
  return await patch('/user/photo', {
    uuid: uuid,
  })
}

export function requestEmailChange (userId, email, password) {
  return patch(`/user/${userId}/email`, {
    email: email,
    password: password,
  })
}

export function requestPasswordChange (oldPassword, newPassword) {
  return patch('/user/current/password', {
    oldPassword: oldPassword,
    newPassword: newPassword,
  })
}

export function get2FAdata () {
  return get('/user/2fa')
}

export function set2FA (password, code, enable) {
  return patch('/user/2fa', {
    password: password,
    code: code,
    enable: enable,
  }, {
    skipErrorNotificationFor: [403], // 403 = TOTP required
  })
}
