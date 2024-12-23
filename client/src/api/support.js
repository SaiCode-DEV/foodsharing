import { post } from './base'

export function createTicket (emailAddress, subject, body, firstName, attachments) {
  return post('/support/ticket', {
    emailAddress,
    subject,
    body,
    firstName,
    attachments,
    userAgent: navigator?.userAgent,
  })
}
