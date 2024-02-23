import Vue from 'vue'
import { listPolls } from '@/api/groups'

export const store = Vue.observable({
  groups: [],
  polls: [],
})

export const getters = {
  get () {
    return store.groups
  },
  getPolls () {
    return store.polls
  },
}

export const mutations = {
  set (groups) {
    store.groups = groups
  },
  async listPolls (groupId) {
    store.polls = await listPolls(groupId)
  },
}

export default { store, getters, mutations }
