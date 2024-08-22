import '@/core'
import '@/globals'
import '@/tablesorter'
import 'jquery.tinymce'
import { GET, goTo, URL_PART } from '@/browser'
import { hideLoader, ifconfirm, pulseError, showLoader } from '@/script'
import { expose } from '@/utils'
import { vueApply, vueRegister } from '@/vue'
import BlogOverview from './components/BlogOverview.vue'
import BlogPost from './components/BlogPost'
import BlogPostList from './components/BlogPostList'
import FileUploadVForm from '@/components/upload/FileUploadVForm'
import { addBlogpost } from '@/api/blog'
import i18n from '@/helper/i18n'
import { url } from '@/helper/urls'
import $ from 'jquery'

expose({
  ifconfirm, _addBlogPost,
})

// TODO: can be removed when there is a Vue form for blog posts
async function _addBlogPost () {
  showLoader()
  const regionId = $('#bezirk_id').val()
  const title = $('#name').val()
  const teaser = $('#teaser').val()
  const content = $('#body').val()
  const picture = $('input[name="picture"]').val()

  try {
    await addBlogpost(regionId, title, teaser, content, picture)
    pulseError(i18n('blog.success.new'))
    goTo(url('blogList'))
  } catch (e) {
    pulseError(i18n('blog.failure.new'))
  }
  hideLoader()
}

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
