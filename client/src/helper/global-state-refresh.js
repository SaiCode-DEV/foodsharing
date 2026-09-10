import DataBells from '@/stores/bells'
import conversationStore from '@/stores/conversations'
import { useUserStore } from '@/stores/user'
import { fetchServerData } from '@/helper/server-data'

const RESYNC_INTERVAL_MS = 60000 // 60 seconds
let isRefreshing = false
let lastRefreshTime = 0

export async function refreshGlobalState () {
  const now = Date.now()
  if (isRefreshing || (now - lastRefreshTime < RESYNC_INTERVAL_MS)) {
    return
  }

  isRefreshing = true

  try {
    await Promise.allSettled([
      DataBells.mutations.fetch(true),
      conversationStore.refreshConversations(),
      useUserStore().fetchMailUnreadCount(true),
      fetchServerData(true),
    ])
    lastRefreshTime = Date.now()
  } finally {
    isRefreshing = false
  }
}

export function checkAndClearDirtyFlag () {
  return new Promise((resolve) => {
    const request = indexedDB.open('fs_sync', 1)
    request.onupgradeneeded = e => {
      e.target.result.createObjectStore('flags')
    }
    request.onsuccess = e => {
      const db = e.target.result
      try {
        const tx = db.transaction('flags', 'readwrite')
        const store = tx.objectStore('flags')
        const getReq = store.get('lastDirty')
        getReq.onsuccess = () => {
          if (getReq.result) {
            store.delete('lastDirty')
            resolve(true)
          } else {
            resolve(false)
          }
        }
        getReq.onerror = () => resolve(false)
      } catch {
        resolve(false)
      }
    }
    request.onerror = () => resolve(false)
  })
}
