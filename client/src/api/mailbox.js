import { get, remove, patch, post } from './base'

export async function getMailUnreadCount () {
  return get('/mailbox/unread-count')
}

export async function setEmailProperties (emailId, isRead = null, folder = null) {
  return patch(`/mailbox/${emailId}`, {
    isRead: isRead !== null ? isRead : undefined,
    folder: folder,
  })
}

export async function deleteEmail (emailId) {
  return remove(`/mailbox/${emailId}`)
}

export async function getAllEmails (mailboxId, folderId) {
  return get(`/mailbox/all/${mailboxId}/${folderId}`)
}

export async function getEmail (emailId) {
  return get(`/mailbox/${emailId}`)
}

export function sendEmail (mailboxId, to, cc, bcc, subject, body, attachments, replyEmailId) {
  return post(`/mailbox/${mailboxId}`, {
    to: to,
    cc: cc,
    bcc: bcc,
    subject: subject,
    body: body,
    attachments: attachments,
    replyEmailId: replyEmailId,
  })
}

export async function listRegions () {
  return get('/mailbox/regions')
}
