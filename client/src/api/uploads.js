import { post, get } from './base'

export async function uploadFile (filename, body) {
  return post('/uploads', {
    filename: filename,
    body: body,
  })
}

export async function getImageMetadata (uuid) {
  return get(`/uploads/${uuid}/metadata`)
}
