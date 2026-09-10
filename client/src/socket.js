import io from 'socket.io-client'

import DataBells from '@/stores/bells'
import conversationStore, { convertMessage } from '@/stores/conversations'
import { refreshGlobalState, checkAndClearDirtyFlag } from '@/helper/global-state-refresh'

const RESYNC_AFTER = 60000 // 60 seconds

export default {
  connect: function () {
    const socket = io.connect(window.location.host, { path: '/websocket/socket.io' })
    let hasConnected = false
    let hiddenAt = null

    document.addEventListener('visibilitychange', async () => {
      socket.emit('visibilitychange', document.hidden) // send tab/window visibility change to socket server, so it can use it to determine if the user is online or not

      if (document.hidden) {
        hiddenAt = Date.now()
        return
      }

      const isDirty = await checkAndClearDirtyFlag()
      if (isDirty || (hiddenAt && Date.now() - hiddenAt > RESYNC_AFTER)) {
        refreshGlobalState()
      }
      hiddenAt = null
    })

    socket.on('connect', () => {
      if (hasConnected) {
        refreshGlobalState()
      }
      hasConnected = true
    })

    socket.on('conv', async function (data) {
      if (data.m === 'push') {
        const obj = data.o
        obj.message = convertMessage(obj.message)
        await conversationStore.newMessageReceived(obj)
      }
    })

    socket.on('bell', function (data) {
      if (data.m === 'update') {
        DataBells.mutations.fetch()
      }
    })
  },
}
