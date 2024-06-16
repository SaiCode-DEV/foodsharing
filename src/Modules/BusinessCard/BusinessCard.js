/* eslint-disable camelcase */
import './BusinessCard.css'
import '@/core'
import '@/globals'
import $ from 'jquery'
import { expose } from '@/utils'

expose({
  u_download,
})

function u_download (short) {
  $('#dlbox').show()
  $('#dlbox a').attr('href', `/user/current/settings?sub=bcard&a=dl&b=${short}`)
}
