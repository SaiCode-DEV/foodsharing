function httpGetVars () {
  const vars = {}
  const strGET = document.location.search.substr(1, document.location.search.length)

  if (strGET !== '') {
    const gArr = strGET.split('&')
    for (let i = 0; i < gArr.length; ++i) {
      const vArr = gArr[i].split('=')
      const v = vArr[1] ?? true
      vars[unescape(vArr[0])] = unescape(v)
    }
  }

  return vars
}

/**
 * Client side navigation changes the url without loading a document, so the parameters have to be
 * read from the current location on every call instead of once when this module is loaded.
 */
export function GET (v) {
  const value = httpGetVars()[v]
  if (!value) { return undefined }
  return value
}

export function URL_PART (index) {
  const parts = document.location.pathname.substring(1).split('/')
  if (!parts[index]) { return undefined }
  return parts[index]
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
