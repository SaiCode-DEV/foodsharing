const CLOSING_BRACKETS = { ')': '(', ']': '[', '}': '{' }
const TRAILING_PUNCTUATION = '.,;:!?\'"'
const URL_PATTERN = /https?:\/\/\S+/g

function trimPunctuation (url) {
  let end = url.length
  while (end > 0 && TRAILING_PUNCTUATION.includes(url[end - 1])) { end-- }

  return url.slice(0, end)
}

/**
 * True if the url ends in a closing bracket that it never opens, which means the
 * bracket belongs to the surrounding text: `[https://example.org]`.
 */
function endsInUnopenedBracket (url) {
  const lastChar = url[url.length - 1]
  const opener = CLOSING_BRACKETS[lastChar]
  if (!opener) { return false }

  const inner = url.slice(0, -1)

  return inner.split(opener).length <= inner.split(lastChar).length
}

/**
 * Cuts everything off the end of a detected url that belongs to the sentence
 * rather than to the url. Brackets the url opens itself are kept, so a link like
 * `wiki/Berlin_(Stadt)` stays intact.
 */
export function trimUrlEnd (url) {
  let trimmed = trimPunctuation(url)
  while (endsInUnopenedBracket(trimmed)) {
    trimmed = trimPunctuation(trimmed.slice(0, -1))
  }

  return trimmed
}

/**
 * Turns the urls in a plain text into links. The text has to be escaped already,
 * the result goes into the document as html.
 */
export function linkifyUrls (text) {
  return text.replace(URL_PATTERN, (match) => {
    const url = trimUrlEnd(match)

    return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>${match.slice(url.length)}`
  })
}
