import { get, put } from './base'

export async function getLocale () {
  return (await get('/locale')).locale
}

export function setLocale (locale) {
  return put('/locale?locale=' + locale, {})
}
