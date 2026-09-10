import { getCurrentInstance } from 'vue'

/**
 * vue-router 3 (Vue 2.7) has no useRoute()/useRouter() composables of its own -
 * $route/$router are only injected onto the instance via the router's mixin.
 * These wrap that lookup so <script setup> components can read them like
 * vue-router 4 code does, without repeating getCurrentInstance() everywhere.
 */
export function useRoute () {
  return getCurrentInstance().proxy.$route
}

export function useRouter () {
  return getCurrentInstance().proxy.$router
}
