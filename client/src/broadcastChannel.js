export const channel = 'BroadcastChannel' in window
  ? new BroadcastChannel('foodsharing')
  : { postMessage: () => {} } // Create a mock object if API is unsupported

channel.onmessage = (event) => channelListeners.forEach((listener) => listener(event))

export const BROADCAST_TYPE = Object.freeze({
  LOGOUT: 0,
  LOGIN: 1,
  UPDATE_CONVERSATIONS: 2,
  UPDATE_BELLS: 3,
})

const channelListeners = [
  (event) => {
    switch (event.data.type) {
      case BROADCAST_TYPE.LOGOUT:
      case BROADCAST_TYPE.LOGIN:
        window.reload()
        break
    }
  },
]

export function storeSynchronizer (broadcastType, propertyKey) {
  // `broadcastChanges` is tells if a change to the watched value should be broadcasted to other tabs.
  // This is usually true, but disabled when recieving a value update from another tab. This way
  // the same change isn't continually broadcasted between multiple tabs.
  // Changes are only applied to the tab that is in focus. Unfocused tabs just save the new value and
  // use it only as soon as the new tab comes into focus again to prevent unnecessary rerenders.
  let broadcastChanges = true
  const deferredNewValues = {}
  return {
    watch: {
      [propertyKey]: {
        handler () {
          if (broadcastChanges) {
            channel.postMessage({ type: broadcastType, [propertyKey]: this[propertyKey] })
          } else {
            broadcastChanges = true
          }
        },
        deep: true,
      },
    },
    created () {
      channelListeners.push((event) => {
        if (event.data.type === broadcastType) {
          broadcastChanges = false // don't broadcast the recieved change
          if (document.hasFocus()) {
            this[propertyKey] = event.data[propertyKey]
          } else {
            deferredNewValues[propertyKey] = event.data[propertyKey]
          }
        }
      })
      window.addEventListener('focus', () => {
        if (deferredNewValues[propertyKey]) {
          this[propertyKey] = deferredNewValues[propertyKey]
          delete deferredNewValues[propertyKey]
        }
      })
    },
  }
}
