import '@/core'
import '@/globals'

import { GET } from '@/browser'
import { vueRegister, vueApply } from '@/vue'

import './Content.css'
import ReleaseNotes from './components/ReleaseNotes.vue'
import ContentList from './components/ContentList'
import Communities from '@/views/pages/Content/Communities.vue'
import ContentEdit from './components/ContentEdit.vue'
import JoinInfo from './components/JoinInfo.vue'
import ContentEntry from '@/components/Content/ContentEntry.vue'
import ContactPage from './components/ContactPage.vue'
import Partner from './components/Partner.vue'

vueRegister({
  ReleaseNotes,
  Communities,
  JoinInfo,
  ContactPage,
  ContentEntry,
  Partner,
  ContentList,
  ContentEdit,
})

document.addEventListener('DOMContentLoaded', () => {
  if (GET('sub') === 'releaseNotes') {
    vueApply('#vue-release-notes')
  } else if (GET('sub') === 'communities') {
    vueApply('#vue-communities')
  } else if (GET('sub') === 'joininfo') {
    vueApply('#vue-join-info')
  } else if (GET('sub') === 'contact') {
    vueApply('#vue-contact-page')
  } else if (document.getElementById('vue-content')) {
    vueApply('#vue-content')
  } else if (document.getElementById('content-partner')) {
    vueApply('#content-partner')
  } else if (GET('sub') === undefined && GET('a') === undefined) {
    vueApply('#content-list')
  } else if (GET('a') === 'edit' || GET('a') === 'new') {
    vueApply('#content-edit')
  }
})
