import { geoapifyApiKey, isDev } from '@/helper/server-data'
import { languageCodeISO } from '@/helper/i18n'

const GEOAPIFY_API_URL = isDev ? '/mock/geocode' : 'https://api.geoapify.com/v1/geocode'

export async function fetchAutocomplete (input) {
  if (!input || input.length < 3) {
    return []
  }
  const language = languageCodeISO()
  const params = new URLSearchParams({
    text: input,
    limit: 5,
    lang: language,
    bias: `countrycode:${language}`,
    filter: 'countrycode:de,at,ch,nl,be,lu,fr,pl,cz,sk,hu',
    apiKey: geoapifyApiKey,
  })
  const response = await fetch(
      `${GEOAPIFY_API_URL}/search?${params}`,
      { limit: 5 },
  )
  try {
    const data = await response.json()
    if (data?.features?.length === 0) {
      return []
    }
    return data?.features.map((item) => item.properties)
  } catch (error) {
    return []
  }
}

export async function fetchReverseGeocode (coords) {
  if (!coords) {
    return null
  }
  const params = new URLSearchParams({
    lat: coords.lat,
    lon: coords.lon,
    lang: languageCodeISO(),
    apiKey: geoapifyApiKey,
  })
  const response = await fetch(
      `${GEOAPIFY_API_URL}/reverse?${params}`,
  )
  try {
    const data = await response.json()
    if (data?.features?.length === 0) {
      return null
    }
    return data?.features[0].properties
  } catch (error) {
    return null
  }
}
