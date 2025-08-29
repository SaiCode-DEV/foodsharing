export function goTo (url) {
  if (url !== '#') {
    document.location.href = url
  }
}

export function isMob () {
  return window.innerWidth < 900
}

const HTTP_GET_VARS = {}
const strGET = document.location.search.substr(1, document.location.search.length)

if (strGET !== '') {
  const gArr = strGET.split('&')
  for (let i = 0; i < gArr.length; ++i) {
    const vArr = gArr[i].split('=')
    const v = vArr[1] ?? true
    HTTP_GET_VARS[unescape(vArr[0])] = unescape(v)
  }
}

export function GET (v) {
  if (!HTTP_GET_VARS[v]) { return undefined }
  return HTTP_GET_VARS[v]
}

const URL_PARTS = document.location.pathname.substring(1).split('/')
export function URL_PART (index) {
  if (!URL_PARTS[index]) { return undefined }
  return URL_PARTS[index]
}

export function setUrlParam (key, value) {
  const url = new URL(window.location.href)
  const params = url.searchParams

  if (value === undefined || value === null) {
    params.delete(key)
  } else {
    params.set(key, value)
  }

  // Update URL without reloading
  history.replaceState(null, '', `${url.pathname}?${params.toString()}${url.hash}`)
}
