import { get, patch, post, put, remove } from './base'

export async function getBlogposts (page) {
  return get(`/blog?offset=${page * 10}&limit=10`)
}

export async function getBlogpost (blogPostId) {
  return get(`/blog/${blogPostId}`)
}

export async function publishBlogpost (blogId, newPublishedState) {
  return put(`/blog/${blogId}/published?isPublished=${newPublishedState}`)
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
