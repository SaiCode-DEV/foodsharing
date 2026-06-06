import Vue from 'vue'
import { listPolls } from '@/api/groups'

export const GROUP_CATEGORY = Object.freeze({
  DEFAULT: { id: null, icon: 'fa-users' },
  DEVELOPMENT: { id: 1, icon: 'fa-tools' },
  EXCHANGE: { id: 2, icon: 'fa-comments' },
  ADMINISTRATIVE: { id: 3, icon: 'fa-cogs' },
  PROJECT: { id: 4, icon: 'fa-rocket' },
  ARCHIVED: { id: 5, icon: 'fa-archive' },
})

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
