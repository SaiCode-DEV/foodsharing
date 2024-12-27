import { get, patch } from './base'

export function getLegalPageData () {
  return get('/legal/page-data')
}

export function updateLegalAcknowledge (policy, notice) {
  return patch('/legal/acknowledge', { policy, notice })
}
