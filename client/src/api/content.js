import { get, patch, remove } from './base'

export const CONTENT_IDS = Object.freeze({
  DONATION: 1,
  CONFIRM_FOODSAVER_QUIZ: 14,
  CONFIRM_STORE_MANAGER_QUIZ: 15,
  LEGAL_FOODSAVER_QUIZ: 30,
  LEGAL_STORE_MANAGER_QUIZ: 31,
})

export async function getContent (contentId) {
  return await get(`/content/${contentId}`)
}

export async function listContent () {
  return await get('/content')
}

export async function deleteContent (contentId) {
  return await remove(`/content/${contentId}`)
}

export async function editContent (contentId, name, title, body) {
  return await patch(`/content/${contentId}`, {
    name: name,
    title: title,
    body: body,
  })
}
