import 'whatwg-fetch'
import '@/sentry'

import '@/style'

import registerServiceWorker from '@/registerServiceWorker'

/*
  Loads a lot of CSS stylings
*/
import './scss/index.scss'

import '@/views/views'

import serverData from '@/helper/server-data'

import socket from '@/socket'

if (!serverData.isDev) {
  registerServiceWorker()
}

if (serverData.user.may) {
  socket.connect()
}
