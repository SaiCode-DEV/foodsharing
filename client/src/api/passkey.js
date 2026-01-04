import { get, post, patch, remove } from './base'

/**
 * Get registration options for creating a new passkey
 */
export async function getRegistrationOptions () {
  return post('/passkey/registration/options')
}

/**
 * Verify and save a new passkey credential
 */
export async function verifyRegistration (credential, name = null) {
  return post('/passkey/registration/verify', { credential, name }, { skipErrorNotificationFor: [400] })
}

/**
 * Get authentication options for passkey login.
 * No email needed - passkeys are usernameless!
 */
export async function getAuthenticationOptions () {
  return post('/passkey/authentication/options')
}

/**
 * Verify passkey authentication and log in
 */
export async function verifyAuthentication (credential) {
  return post('/passkey/authentication/verify', { credential })
}

/**
 * List all passkeys for the current user
 */
export async function listPasskeys () {
  return get('/passkey/list')
}

/**
 * Delete a passkey
 */
export async function deletePasskey (id) {
  return remove(`/passkey/${id}`)
}

/**
 * Rename a passkey
 */
export async function renamePasskey (id, name) {
  return patch(`/passkey/${id}`, { name })
}
