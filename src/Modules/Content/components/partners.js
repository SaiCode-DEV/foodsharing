import { getContent } from '@/api/content'

function extractJSON (htmlString) {
  // Remove HTML tags and decode entities
  const div = document.createElement('div')
  div.innerHTML = htmlString
  const rawText = div.textContent || div.innerText
  try {
    // Parse the cleaned text as JSON
    return JSON.parse(rawText)
  } catch (e) {
    console.error('Failed to parse content as JSON:', e)
    return null
  }
}

export async function getPartners (id = 10) {
  const content = await getContent(id)
  const parsedContent = extractJSON(content.body)
  return parsedContent
}
