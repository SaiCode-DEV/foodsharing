import { post } from './base'

export async function sendTestEmail (address, subject, message) {
  return (post('/newsletter/test', {
    address,
    subject,
    message,
  }))
}
