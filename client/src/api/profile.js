import { remove } from './base'

export function removeUserFromBounceList (userId) {
  return remove(`/users/${userId}/email-bounce`)
}
