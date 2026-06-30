import '@/core'
import '@/globals'
import { GET, URL_PART } from '@/browser'
import { vueApply, vueRegister } from '@/vue'
import BlogOverview from './components/BlogOverview.vue'
import BlogPost from './components/BlogPost'
import BlogPostList from './components/BlogPostList'
import BlogEditForm from '@/components/Blog/BlogEditForm.vue'

vueRegister({
  BlogOverview,
  BlogEditForm,
  BlogPost,
  BlogPostList,
})
document.addEventListener('DOMContentLoaded', () => {
  if (GET('sub') === 'manage') {
    vueApply('#vue-blog-overview') // BlogOverview
  } else if (GET('sub') === 'add' || GET('sub') === 'edit') {
    vueApply('#blog-edit-form')
  } else if (GET('sub') === 'read' || URL_PART(1) !== undefined) {
    vueApply('#blog-post')
  } else {
    vueApply('#blog-post-list')
  }
})
