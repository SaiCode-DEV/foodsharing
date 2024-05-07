/* eslint-disable camelcase */

/*
  Make some things from the webpack environment available globally on the window object.

  This is to allow webpack-enabled pages to still have a few bits of inline js:
  - inline click handlers
  - addJs scripts
  - addJsFunc scripts

*/

import $ from 'jquery'

import conv from '@/conv'
import socket from '@/socket'

import { expose } from '@/utils'

import {
  chat,
  pulseInfo,
  pulseError,
  pulseSuccess,
  profile,
  goTo,
  reload,
  ajreq,
  ajax,
  showLoader,
  hideLoader,
  wantToHelpStore,
  withdrawStoreRequest,
  error,
} from '@/script'

expose({
  $,
  jQuery: $,
  chat,
  pulseInfo,
  pulseError,
  pulseSuccess,
  profile,
  goTo,
  reload,
  ajreq,
  ajax,
  showLoader,
  hideLoader,
  wantToHelpStore,
  withdrawStoreRequest,
  conv,
  error,
  sock: socket,
})
