import { get, patch, post, remove } from './base'

export async function getBlogposts (page) {
  return get(`/blog?page=${page}`)
}

export async function getBlogpost (blogPostId) {
  return get(`/blog/${blogPostId}`)
}

export async function publishBlogpost (blogId, newPublishedState) {
  return patch(`/blog/${blogId}`, { isPublished: +newPublishedState })
}

export async function deleteBlogpost (blogId) {
  return remove(`/blog/${blogId}`)
}

export async function addBlogpost (regionId, title, teaser, content, picture) {
  return post('/blog', {
    regionId: regionId,
    title: title,
    teaser: teaser,
    content: content,
    picture: picture,
  })
}
