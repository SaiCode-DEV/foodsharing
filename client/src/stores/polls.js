import Vue from 'vue'
import { listCurrentPolls } from '@/api/voting'

export const VOTING_TYPE = Object.freeze({
  SELECT_ONE_CHOICE: 0,
  SELECT_MULTIPLE: 1,
  THUMB_VOTING: 2,
  SCORE_VOTING: 3,
})

export const store = Vue.observable({
  polls: null,
})

export const getters = {
  getPolls () {
    return store.polls
  },
}

export const mutations = {
  async fetchPolls () {
    store.polls = await listCurrentPolls()
  },
}

export default { store, getters, mutations }
