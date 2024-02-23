import { patch, get } from './base'

export function updateInvitationResponse (eventId, status) {
  return patch(`/users/current/events/${eventId}/invitation`, { status: status })
}

export function listEvents (regionId) {
  return get(`/region/${regionId}/events`)
}
