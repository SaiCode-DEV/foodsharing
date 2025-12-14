import { patch, get, post } from './base'

export function updateInvitationResponse (eventId, status) {
  return patch(`/users/current/events/${eventId}/invitation`, { status })
}

export function listEvents (regionId) {
  return get(`/region/${regionId}/events`)
}

export function addEvent (event) {
  return post('/events', event)
}

export function editEvent (event) {
  return patch(`/events/${event.id}`, event)
}
