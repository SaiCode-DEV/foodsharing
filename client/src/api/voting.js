import { get, patch, post, put, remove } from './base'

export async function getPoll (pollId) {
  return get(`/polls/${pollId}`)
}

export async function listPolls (groupId) {
  return get(`/groups/${groupId}/polls`)
}

export async function listCurrentPolls () {
  return get('/user/current/polls')
}

export function createPoll (regionId, name, description, startDate, endDate, scope, type, options, shuffleOptions, notifyVoters) {
  return post('/polls', {
    regionId,
    name,
    description,
    startDate: startDate.toISOString(),
    endDate: endDate.toISOString(),
    scope,
    type,
    options,
    shuffleOptions,
    notifyVoters,
  })
}

export function editPoll (pollId, name, description, options, shuffleOptions) {
  return patch(`/polls/${pollId}`, {
    name,
    description,
    options,
    shuffleOptions,
  })
}

export async function deletePoll (pollId) {
  return remove(`/polls/${pollId}`)
}

export async function vote (pollId, options) {
  return put(`/polls/${pollId}/vote`, {
    options,
  })
}
