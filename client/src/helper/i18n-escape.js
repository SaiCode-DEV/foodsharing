// vue-i18n v9 reads '@' as linked-message syntax, so literal '@'s must be escaped
// to "{'@'}" when messages are loaded (#2718). Import-free so Playwright specs can
// exercise it directly (#2767).
export function escapeLinkedTokens (value) {
  if (typeof value === 'string') {
    return value.includes('@') ? value.replace(/@/g, "{'@'}") : value
  }
  if (Array.isArray(value)) {
    return value.map(escapeLinkedTokens)
  }
  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.keys(value).map(key => [key, escapeLinkedTokens(value[key])]),
    )
  }
  return value
}
