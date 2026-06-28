import { get, post, patch, remove } from './base'

export async function listBlocklistEntries () {
  return get('/admin/emailblocklist')
}

export async function getBlocklistEntry (id) {
  return get(`/admin/emailblocklist/${id}`)
}

export async function createBlocklistEntry (data) {
  return post('/admin/emailblocklist', data)
}

export async function updateBlocklistEntry (id, data) {
  return patch(`/admin/emailblocklist/${id}`, data)
}

export async function deleteBlocklistEntry (id) {
  return remove(`/admin/emailblocklist/${id}`)
}
