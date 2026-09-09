import 'whatwg-fetch'
import 'object.groupby/auto'
import '@/sentry'

import '@/style'

import { scheduleSWRegistration } from '@/registerServiceWorker'

/*
  Loads a lot of CSS stylings
*/
import './scss/index.scss'

import serverData, { startServerDataPolling } from '@/helper/server-data'

import socket from '@/socket'

if (!serverData.isDev) {
  scheduleSWRegistration()
}

if (serverData.user.may) {
  socket.connect()
}

startServerDataPolling()
