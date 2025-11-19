import { get, patch, post, remove } from './base'

export function login (email, password, code, rememberMe) {
  return post('/user/login', { email, password, code, remember_me: rememberMe }, {
    disableLoginRedirect: true,
    skipErrorNotificationFor: [403], // 403 = TOTP required
  })
}

export function getUser () {
  return get('/user/current')
}

export function getBasicUser (id) {
  return get(`/user/${id}`)
}

export function getDetails () {
  return get('/user/current/details')
}

export function deleteUser (id, reason) {
  return remove(`/user/${id}`, {
    reason: reason,
  })
}

export function getUserNames (ids) {
  return get(`/user/names/${ids.join('-')}`)
}

export function registerUser (firstName, lastName, email, password, gender, birthdate, mobilePhone, subscribeNewsletter) {
  return post('/user', {
    firstname: firstName,
    lastname: lastName,
    email: email,
    password: password,
    gender: gender,
    birthdate: birthdate,
    mobilePhone: mobilePhone,
    subscribeNewsletter: subscribeNewsletter,
  })
}

export function patchUserProfile (userId, data) {
  return patch(`/user/${userId}/profile`, data)
}

export function getUserProfileSettings (userId) {
  return get(`/user/${userId}/profile`)
}

export function testRegisterEmail (email) {
  return post('/user/isvalidemail', { email: email }, { skipErrorNotificationFor: [400] })
    .then(response => response)
    .catch(error => {
      if (error && error.response && error.response.status === 400) {
        return { valid: false, error: error.response.data }
      }
      throw error
    })
}

export function setSleepStatus (mode, from, to, message) {
  return patch('/user/sleepmode', {
    mode: mode,
    from: from,
    to: to,
    message: message,
  })
}

export function requestPasswordReset (email) {
  return post('/user/password-reset', { email }, {
    disableLoginRedirect: true,
  })
}

export function resetPassword (resetToken, password, totpCode = null) {
  return post('/user/password-reset/confirm', {
    'reset-token': resetToken,
    password,
    'totp-code': totpCode,
  }, {
    disableLoginRedirect: true,
    skipErrorNotificationFor: [400, 403],
  })
}

export function validateResetToken (resetToken) {
  return get(`/user/password-reset/validate?reset-token=${encodeURIComponent(resetToken)}`, {
    disableLoginRedirect: true,
  })
}
