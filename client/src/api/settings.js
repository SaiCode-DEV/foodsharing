import { patch } from './base'

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
