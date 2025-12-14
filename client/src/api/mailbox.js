import { get, remove, patch, post } from './base'

export async function getMailUnreadCount () {
  return get('/mailbox/unread-count')
}

export async function setEmailProperties (emailId, isRead = null, folder = null) {
  return patch(`/mailbox/${emailId}`, {
    isRead: isRead !== null ? isRead : undefined,
    folder,
  })
}

export async function deleteEmail (emailId) {
  return remove(`/mailbox/${emailId}`)
}

export async function getAllEmails (mailboxId, folderId, page, pageSize) {
  return get(`/mailbox/all/${mailboxId}/${folderId}?page=${page}&pageSize=${pageSize}`)
}

export async function getEmail (emailId) {
  return get(`/mailbox/${emailId}`)
}

export function sendEmail (mailboxId, to, cc, bcc, subject, body, attachments, replyEmailId) {
  return post(`/mailbox/${mailboxId}`, {
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
  return get('/mailbox/regions')
}
