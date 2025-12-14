import markdownIt from 'markdown-it'

const md = markdownIt('zero', {
  html: true,
  breaks: true,
  linkify: true,
  typopgrapher: true,
  quotes: '“”‘’',
})
  .enable([
    'heading',
    'emphasis',
    'strikethrough',
    'blockquote',
    'newline',
    'image',
    'link',
    'backticks',
    'linkify',
    'hr',
    'list',
    'fence',
    'code',
    'escape',
  ])

const storageKey = 'linkifyUserNames'
md.linkify.data = {
  storageKey,
  userNames: JSON.parse(sessionStorage.getItem(storageKey)) ?? {},
  missingUserNames: new Set(),
  fetchResolves: new Set(),
}

md.linkify.add('@', {
  validate: function (text, pos, self) {
    self.re.profileId ??= /^\d{3,}/
    const tail = text.slice(pos)
    if (self.re.profileId.test(tail)) {
      return tail.match(self.re.profileId)[0].length
    }
    return false
  },
  async normalize (match, self) {
    const id = +match.url.slice(1)
    match.url = '/profile/' + id
    if (self.data.userNames[id]) {
      match.text = '@' + self.data.userNames[id]
    } else if (!(id in self.data.userNames)) {
      self.data.missingUserNames.add(id)
    } else {
      match.url = '#invalid-user'
    }
  },
})

// Add missing top level domains
md.linkify.tlds(['network'], true)

// Open external links in new tab
const defaultRender = md.renderer.rules.link_open || function (tokens, idx, options, env, self) {
  return self.renderToken(tokens, idx, options)
}

md.renderer.rules.link_open = function (tokens, idx, options, env, self) {
  const token = tokens[idx]
  const hrefIndex = token.attrIndex('href')

  if (hrefIndex >= 0) {
    const href = token.attrs[hrefIndex][1]
    // Check if link is external (starts with http:// or https:// and not same origin)
    if (href.startsWith('http://') || href.startsWith('https://')) {
      try {
        const url = new URL(href)
        const isExternal = url.origin !== window.location.origin
        if (isExternal) {
          token.attrPush(['target', '_blank'])
          token.attrPush(['rel', 'noopener noreferrer'])
        }
      } catch (e) {
        // Invalid URL encountered while parsing link; log for debugging purposes
        console.warn(`Invalid URL in markdown link: "${href}". Error:`, e)
      }
    }
  }

  return defaultRender(tokens, idx, options, env, self)
}

export default md
