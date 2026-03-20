import io from 'socket.io-client'

// eslint-disable-next-line camelcase
import { session_id } from '@/script'

import DataBells from '@/stores/bells'
import conversationStore, { convertMessage } from '@/stores/conversations'

export default {
  connect: function () {
    const socket = io.connect(window.location.host, { path: '/websocket/socket.io' })
    document.addEventListener('visibilitychange', () => {
      socket.emit('visibilitychange', document.hidden) // send tab/window visibility change to socket server, so it can use it to determine if the user is online or not
    })

    socket.on('connect', function () {
      // console.log('WebSocket connected.')
      socket.emit('register', session_id())
    })

    socket.on('conv', async function (data) {
      if (data.m === 'push') {
        const obj = data.o
        obj.message = convertMessage(obj.message)
        await conversationStore.newMessageReceived(obj)
      }
    })

    socket.on('info', function (data) {
      if (data.m === 'badge') {
        // info.badge('info', data.o.count)
      }
    })

    socket.on('bell', function (data) {
      if (data.m === 'update') {
        DataBells.mutations.fetch()
      }
    })
  },
}
