import { remove } from './base'

export function removeUserFromBounceList (userId) {
  return remove(`/user/${userId}/emailbounce`)
}
