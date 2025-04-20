import Vue from 'vue'

export const PROFILE_STORE_TEAM_STATE = Object.freeze({
  REQUESTED: 0,
  ACTIVE: 1,
  JUMPER: 2,
  INVITED: 3,
  MANAGE_ROLE: 4,
})
export default new Vue({
  data: {
    profiles: {},
  },
  methods: {
    updateFrom (profiles) {
      if (Array.isArray(profiles)) {
        for (const profile of profiles) {
          Vue.set(this.profiles, profile.id, convertProfile(profile))
        }
      }
    },
  },
})

export function convertProfile (val) {
  if (Array.isArray(val)) {
    return val.map(convertProfile)
  } else {
    return {
      ...val,
    }
  }
}
