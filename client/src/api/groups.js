import { remove, patch, post, get } from './base'

export function deleteGroup (id) {
  return remove(`/regions/${id}`)
}

export function addMember (groupId, memberId) {
  return post(`/groups/${groupId}/members/${memberId}`)
}

export function updateGroup (groupId, name, description, photo, applyType, applicationPrompt, groupCategory) {
  return patch(`/groups/${groupId}`, {
    name,
    description,
    photo,
    applyType,
    applicationPrompt: applicationPrompt || null,
    groupCategory,
  })
}

export function sendMail (groupId, message) {
  return post(`/groups/${groupId}/mail`, {
    message,
  })
}

export function sendRequest (groupId, application) {
  return post(`/groups/${groupId}/applications`, { application })
}

export function listPolls (groupId) {
  return get(`/groups/${groupId}/polls`)
}

export function listGroups (regionId) {
  return get(`/regions/${regionId}/groups`)
}
