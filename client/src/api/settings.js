import { patch } from './base'

export async function setProfilePhoto (uuid) {
  return await patch('/user/photo', {
    uuid: uuid,
  })
}

export function requestEmailChange (email, password) {
  return patch('/user/current/email', {
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
