import { getContent } from '@/api/content'

function extractJSON (htmlString) {
  // Remove HTML tags and decode entities
  const div = document.createElement('div')
  div.innerHTML = htmlString
  let rawText = div.textContent || div.innerText

  // Replace non-breaking spaces (U+00A0) with regular spaces
  rawText = rawText.replace(/\u00a0/g, ' ')

  try {
    // Parse the cleaned text as JSON
    return JSON.parse(rawText)
  } catch (e) {
    console.error('Failed to parse 2FA apps content as JSON:', e)
    console.error('Raw text:', rawText)
    return null
  }
}

export async function getTwoFactorApps (id = 97) {
  const content = await getContent(id)
  const parsedContent = extractJSON(content.body)
  return parsedContent
}
