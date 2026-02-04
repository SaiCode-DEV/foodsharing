import { get, remove, patch, post } from './base'

export async function getMailUnreadCount () {
  return (await get('/mailboxes/unread-count')).unreadCount
}

export async function setEmailProperties (mailId, isRead = null, folder = null) {
  return patch(`/mailboxes/mails/${mailId}`, {
    isRead: isRead !== null ? isRead : undefined,
    folder,
  })
}

export async function deleteEmail (mailId) {
  return remove(`/mailboxes/mails/${mailId}`)
}

export async function getAllEmails (mailboxId, folderId, offset, limit) {
  return get(`/mailboxes/${mailboxId}/folders/${folderId}/mails?offset=${offset}&limit=${limit}`)
}

export async function getEmail (mailId) {
  return get(`/mailboxes/mails/${mailId}`)
}

export function sendEmail (mailboxId, to, cc, bcc, subject, body, attachments, replyEmailId) {
  return post(`/mailboxes/${mailboxId}/mails`, {
    to,
    cc,
    bcc,
    subject,
    body,
    attachments,
    replyEmailId,
  })
}

export async function listRegions () {
  return get('/regions/mailboxes')
}
