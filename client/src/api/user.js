import { get, patch, post, remove } from './base'

export function login (email, password, code, rememberMe) {
  return post('/login', { email, password, code, rememberMe }, {
    disableLoginRedirect: true,
    skipErrorNotificationFor: [403], // 403 = TOTP required
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

export function deleteUser (id, reason, password = null) {
  return remove(`/users/${id}`, {
    reason,
    password,
  }, { skipErrorNotificationFor: [401] })
}

export function getUserNames (ids) {
  return get(`/users/${ids.join(',')}/names`)
}

export function registerUser (firstName, lastName, email, password, gender, birthdate, mobilePhone, subscribeNewsletter) {
  return post('/users', {
    firstName,
    lastName,
    email,
    password,
    gender,
    birthdate,
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

export function testRegisterEmail (email) {
  return post('/users/registration/email-checker', { email }, { skipErrorNotificationFor: [400] })
    .then(response => response)
    .catch(error => {
      if (error && error.response && error.response.status === 400) {
        return { valid: false, error: error.response.data }
      }
      throw error
    })
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
