import '@/core'
import '@/globals'
import '@/tablesorter'

import 'jquery.tinymce' // cannot go earlier!

import { GET } from '@/browser'
import { expose } from '@/utils'
import { hideLoader, ifconfirm, pulseError, pulseSuccess, showLoader } from '@/script'
import { vueRegister, vueApply } from '@/vue'

import './Content.css'
import ReleaseNotes from './components/ReleaseNotes.vue'
import ContentList from './components/ContentList'
import Communities from '@/views/pages/Content/Communities.vue'
import i18n from '@/helper/i18n'
import $ from 'jquery'
import { editContent } from '@/api/content'

expose({
  ifconfirm, _editContent,
})

if (GET('sub') === 'releaseNotes') {
  vueRegister({
    ReleaseNotes,
  })
  vueApply('#vue-release-notes')
} else if (GET('sub') === 'communities') {
  vueRegister({ Communities })
  vueApply('#vue-communities')
} else if (GET('sub') === undefined && GET('a') === undefined) {
  vueRegister({
    ContentList,
  })
  vueApply('#content-list', true)
}

/**
 * @deprecated Can be removed when the content edit form was rewritten in Vue.
 */
async function _editContent (contentId) {
  showLoader()
  try {
    const name = $('#name').val()
    const title = $('#title').val()
    const body = $('#body')[0].value
    await editContent(contentId, name, title, body)
    pulseSuccess(i18n('content.edit_success'))
  } catch (e) {
    pulseError(i18n('error_unexpected'))
  }
  hideLoader()
}
