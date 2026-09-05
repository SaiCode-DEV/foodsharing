import { get, patch, post, remove } from './base'

export const CONTENT_IDS = Object.freeze({
  DONATION: 5,
  DONATION_CAMPAIGN_BLOCK: 100,
  DONATION_FRIENDSHIP_CIRCLE_BLOCK: 101,
  DONATION_ONE_TIME_BLOCK: 102,
  CONFIRM_FOODSAVER_QUIZ: 14,
  CONFIRM_STORE_MANAGER_QUIZ: 15,
  LEGAL_FOODSAVER_QUIZ: 30,
  LEGAL_STORE_MANAGER_QUIZ: 31,
  PRIVACY_NOTICE_CONTENT: 64,
  PRIVACY_POLICY_CONTENT: 28,
  PRIVACY_POLICY_CHANGES: 96,
})

export async function getContent (contentIds) {
  const param = Array.isArray(contentIds) ? contentIds : [contentIds]
  return await get(`/contents/${param}`)
}

export async function listContent () {
  return await get('/contents')
}

export async function deleteContent (contentId) {
  return await remove(`/contents/${contentId}`)
}

export async function editContent (contentId, contentData) {
  return await patch(`/contents/${contentId}`, contentData)
}

export async function addContent (contentData) {
  return await post('/contents', contentData)
}
