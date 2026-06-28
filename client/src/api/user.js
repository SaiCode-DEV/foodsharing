import { HTTP_RESPONSE } from '@/consts'
import { get, patch, post, remove } from './base'

export function login (email, password, code, rememberMe) {
  return post('/login', { email, password, code, rememberMe }, {
    disableLoginRedirect: true,
    skipErrorNotificationFor: [HTTP_RESPONSE.FORBIDDEN], // 403 = TOTP required
  })
}

export function getUser () {
  return get('/user/current')
}

export function getBasicUser (id) {
  return get(`/users/${id}`)
}

export function getDetails () {
  return get('/users/current/details')
}

export function deleteUser (id, reason, unsubscribeNewsletter, password = null, blockmail = false) {
  return remove(`/users/${id}`, {
    reason,
    password,
    unsubscribeNewsletter,
    blockmail,
  }, { skipErrorNotificationFor: [401] })
}

export function getUserNames (ids) {
  return get(`/users/${ids.join(',')}/names`)
}

export function registerUser (firstName, lastName, token, password, gender, birthdate, mobilePhone, subscribeNewsletter) {
  return post('/users', {
    firstName,
    lastName,
    token,
    password,
    gender,
    birthdate: birthdate.toISOString().substring(0, 10),
    mobilePhone,
    subscribeNewsletter: !!subscribeNewsletter,
  })
}

export function patchUserProfile (userId, data) {
  return patch(`/users/${userId}/profile`, data)
}

export function getUserProfileSettings (userId) {
  return get(`/users/${userId}/profile-settings`)
}

export function initialiseRegistration (email) {
  return post('/users/registration', { email }, { skipErrorNotificationFor: [400, 403] })
}

export function setSleepStatus (mode, from, to, message) {
  return patch('/users/current/sleep-mode', {
    mode,
    from,
    to,
    message,
  })
}

export function requestPasswordReset (email) {
  return post('/users/password-reset', { email }, {
    disableLoginRedirect: true,
  })
}

export function resetPassword (resetToken, password, totpCode = null) {
  return post('/users/password-reset/confirmation', {
    resetToken,
    password,
    totpCode,
  }, {
    disableLoginRedirect: true,
    skipErrorNotificationFor: [400, 403],
  })
}

export function validateResetToken (resetToken) {
  return get(`/users/password-reset/validation?token=${encodeURIComponent(resetToken)}`)
}
