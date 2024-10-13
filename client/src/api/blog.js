import { get, patch, post, remove } from './base'

export async function getBlogposts (page) {
  return get(`/blog?page=${page}`)
}

export async function getBlogpost (blogPostId) {
  return get(`/blog/${blogPostId}`)
}

export async function publishBlogpost (blogId, regionId, newPublishedState) {
  return patch(`/blog/${blogId}/publish`, { regionId, isPublished: newPublishedState })
}

export async function deleteBlogpost (blogId) {
  return remove(`/blog/${blogId}`)
}

export async function addBlogpost (regionId, blogPostData) {
  return post('/blog', { regionId, ...blogPostData })
}

export async function editBlogpost (blogId, regionId, blogPostData) {
  return patch(`/blog/${blogId}`, { regionId, ...blogPostData })
}
