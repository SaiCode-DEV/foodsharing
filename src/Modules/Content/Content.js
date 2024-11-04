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

if (GET('sub') === 'releaseNotes') {
  vueRegister({
    ReleaseNotes,
  })
  vueApply('#vue-release-notes')
} else if (GET('sub') === 'communities') {
  vueRegister({ Communities })
  vueApply('#vue-communities')
} else if (GET('sub') === 'joininfo') {
  vueRegister({ JoinInfo })
  vueApply('#vue-join-info')
} else if (document.getElementById('vue-content')) {
  vueRegister({
    ContentEntry,
  })
  vueApply('#vue-content')
} else if (GET('sub') === undefined && GET('a') === undefined) {
  vueRegister({
    ContentList,
  })
  vueApply('#content-list', true)
} else if (GET('a') === 'edit' || GET('a') === 'new') {
  vueRegister({ ContentEdit })
  vueApply('#content-edit')
}
