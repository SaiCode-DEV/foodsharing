import { get, patch, post } from './base'

export function listChains () {
  return get('/chains')
}

export async function listChainStores (chainId) {
  return await get(`/chains/${chainId}/stores`)
}

export async function createChain (data) {
  return await post('/chains', data)
}

export async function editChain (id, data) {
  return await patch(`/chains/${id}`, data)
}
