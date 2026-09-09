import { del, reactive, set } from 'vue'
import { setLang } from './date-formatter'

const SERVER_DATA_CACHE_KEY = 'serverData'
const fallbackServerData = {
  user: {
    id: null,
    firstname: '',
    lastname: '',
    may: false,
    homeRegionId: null,
    hasMailbox: false,
    isFoodsaver: false,
    verified: false,
    avatar: null,
  },
  permissions: null,
  page: 'index',
  subPage: 'index',
  locations: null,
  ravenConfig: null,
  version: 'unknown',
  isDev: false,
  isTest: false,
  locale: 'de',
  geoapifyApiKey: null,
  groups: [],
  regions: [],
}

const serverData = reactive(loadCachedServerData())
setLang(serverData.locale)
let serverDataPromise = null
let serverDataTimestamp = 0
let serverDataPollInterval = null

function isObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value)
}

function reconcileServerData(target, source) {
  for (const key of Object.keys(target)) {
    if (!(key in source)) {
      del(target, key)
    }
  }

  for (const [key, value] of Object.entries(source)) {
    const currentValue = target[key]

    if (Array.isArray(currentValue) && Array.isArray(value)) {
      currentValue.splice(0, currentValue.length, ...value)
    } else if (isObject(currentValue) && isObject(value)) {
      reconcileServerData(currentValue, value)
    } else if (currentValue !== value) {
      set(target, key, value)
    }
  }

  return target
}

function loadCachedServerData() {
  try {
    const cached = JSON.parse(localStorage.getItem(SERVER_DATA_CACHE_KEY))
    if (!isObject(cached)) {
      return fallbackServerData
    }

    const locale = cached.locale || fallbackServerData.locale
    const isLoggedIn = typeof document !== 'undefined' && document.body?.classList.contains('loggedin')
    if (!isLoggedIn) {
      localStorage.setItem(SERVER_DATA_CACHE_KEY, JSON.stringify({ locale }))
      return { ...fallbackServerData, locale }
    }

    return {
      ...fallbackServerData,
      user: isObject(cached.user) ? { ...fallbackServerData.user, ...cached.user } : fallbackServerData.user,
      permissions: cached.permissions ?? null,
      locations: cached.locations ?? null,
      locale,
      groups: Array.isArray(cached.groups) ? cached.groups : [],
      regions: Array.isArray(cached.regions) ? cached.regions : [],
    }
  } catch {
    return fallbackServerData
  }
}

function persistServerData(data) {
  const cache = {
    user: data.user,
    permissions: data.permissions,
    locations: data.locations,
    locale: data.locale,
    groups: data.groups,
    regions: data.regions,
  }
  try {
    localStorage.setItem(SERVER_DATA_CACHE_KEY, JSON.stringify(cache))
  } catch { }
}

function clearServerDataRequestCache() {
  serverDataPromise = null
  serverDataTimestamp = 0
}

async function requestServerData() {
  // using fetch instead of axios to use preload
  const response = await fetch('/api/server/data')
  if (!response.ok) {
    throw new Error(`Failed to load server data: ${response.status}`)
  }
  return response.json()
}

export async function fetchServerData(force = false) {
  const now = Date.now()
  const cacheTtl = 5000 // 5 seconds

  if (force) {
    clearServerDataRequestCache()
  }

  if (serverDataPromise && serverDataTimestamp && (now - serverDataTimestamp < cacheTtl)) {
    return serverDataPromise
  }

  serverDataTimestamp = now
  serverDataPromise = requestServerData()
    .then(data => {
      reconcileServerData(serverData, data)
      setLang(serverData.locale)
      persistServerData(data)
      return serverData
    })
    .catch(error => {
      serverDataPromise = null
      throw error
    })

  return serverDataPromise
}

export function clearServerDataCache() {
  clearServerDataRequestCache()
  try {
    localStorage.removeItem(SERVER_DATA_CACHE_KEY)
  } catch { }
}

const POLL_INTERVAL = 2 * 60 * 1000 // 2 minutes

export function startServerDataPolling() {
  if (serverDataPollInterval) {
    return
  }
  serverDataPollInterval = setInterval(() => {
    fetchServerData(true)
      .catch(error => console.error('Failed to refresh server data:', error))
  }, POLL_INTERVAL)
}

try {
  await fetchServerData()
} catch (error) {
  console.error('Failed to load initial server data:', error)
}

export const { isDev, isTest, geoapifyApiKey } = serverData
export default serverData
