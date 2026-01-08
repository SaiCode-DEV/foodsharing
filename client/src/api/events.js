import { patch, get, post, put } from './base'

export function updateInvitationResponse (eventId, status) {
  return put(`/events/${eventId}/invitation?status=${status}`)
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
