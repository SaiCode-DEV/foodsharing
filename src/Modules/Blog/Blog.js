import '@/core'
import '@/globals'
import '@/tablesorter'
import 'jquery.tinymce'
import { GET, URL_PART } from '@/browser'
import { ifconfirm } from '@/script'
import { expose } from '@/utils'
import { vueApply, vueRegister } from '@/vue'
import BlogOverview from './components/BlogOverview.vue'
import BlogPost from './components/BlogPost'
import BlogPostList from './components/BlogPostList'
import FileUploadVForm from '@/components/upload/FileUploadVForm'

expose({
  ifconfirm,
})

if (GET('sub') === 'manage') {
  vueRegister({
    BlogOverview,
  })
  vueApply('#vue-blog-overview') // BlogOverview
} else if (GET('sub') === 'add' || GET('sub') === 'edit') {
  vueRegister({
    FileUploadVForm,
  })
  vueApply('#image-upload')
} else if (GET('sub') === 'read' || URL_PART(1) !== undefined) {
  vueRegister({
    BlogPost,
  })
  vueApply('#blog-post')
} else {
  vueRegister({
    BlogPostList,
  })
  vueApply('#blog-post-list')
}
