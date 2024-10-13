import '@/core'
import '@/globals'
import { GET, URL_PART } from '@/browser'
import { vueApply, vueRegister } from '@/vue'
import BlogOverview from './components/BlogOverview.vue'
import BlogPost from './components/BlogPost'
import BlogPostList from './components/BlogPostList'
import BlogEditForm from '@/components/Blog/BlogEditForm.vue'

if (GET('sub') === 'manage') {
  vueRegister({
    BlogOverview,
  })
  vueApply('#vue-blog-overview') // BlogOverview
} else if (GET('sub') === 'add' || GET('sub') === 'edit') {
  vueRegister({
    BlogEditForm,
  })
  vueApply('#blog-edit-form')
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
