import { get } from './base'

export async function search (query, global = false) {
  return await get(`/search/all?q=${encodeURIComponent(query)}${global ? '&global' : ''}`)
}

export async function searchUser (query, regionId = null) {
  let path = `/search/user?q=${encodeURIComponent(query)}`
  if (regionId !== null) {
    path += `&regionId=${regionId}`
  }
  return await get(path)
}

export async function getSearchIndex () {
  return await get('/search/index')
}

export async function searchForum (groupId, subforumId, query, searchBody = false) {
  return await get(`/search/forum/${groupId}/${subforumId}?q=${encodeURIComponent(query)}&searchBody=${searchBody ? '1' : '0'}`)
}
