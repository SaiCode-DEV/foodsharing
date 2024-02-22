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
    // TODO save regex
    const tail = text.slice(pos)
    if (/^\d+/.test(tail)) {
      return tail.match(/\d+/)[0].length
    }
    return false
  },
  async normalize (match, self) {
    const id = match.url.slice(1)
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

export default md
