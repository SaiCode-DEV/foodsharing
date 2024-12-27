import { get, patch, post, remove } from './base'

export const CONTENT_IDS = Object.freeze({
  DONATION: 1,
  CONFIRM_FOODSAVER_QUIZ: 14,
  CONFIRM_STORE_MANAGER_QUIZ: 15,
  LEGAL_FOODSAVER_QUIZ: 30,
  LEGAL_STORE_MANAGER_QUIZ: 31,
  PRIVACY_NOTICE_CONTENT: 64,
  PRIVACY_POLICY_CONTENT: 28,
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

export async function editContent (contentId, contentData) {
  return await patch(`/content/${contentId}`, contentData)
}

export async function addContent (contentData) {
  return await post('/content', contentData)
}
