/* eslint-disable camelcase */

/*
  Make some things from the webpack environment available globally on the window object.

  This is to allow webpack-enabled pages to still have a few bits of inline js:
  - inline click handlers
  - addJs scripts
  - addJsFunc scripts

*/

import socket from '@/socket'

import { expose } from '@/utils'

import {
  chat,
  pulseInfo,
  pulseError,
  pulseSuccess,
  pulseWarning,
  closeNotification,
  profile,
  goTo,
  reload,
  showLoader,
  hideLoader,
  wantToHelpStore,
  withdrawStoreRequest,
} from '@/script'

expose({
  chat,
  pulseInfo,
  pulseError,
  pulseSuccess,
  pulseWarning,
  closeNotification,
  profile,
  goTo,
  reload,
  showLoader,
  hideLoader,
  wantToHelpStore,
  withdrawStoreRequest,
  sock: socket,
})
