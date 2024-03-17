import { get, patch, remove } from './base'

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
