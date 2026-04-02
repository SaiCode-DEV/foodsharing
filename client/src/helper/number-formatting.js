import serverData from './server-data'

const narrowNonBreakingSpace = '\u202F'
const fileSizeUnits = ['B', 'kB', 'MB', 'GB']

export function formatFileSize (bytes, { decimals = 1, base = 1024, locale = serverData.locale } = {}) {
  let magnitude = 0
  for (let u = 0; u < fileSizeUnits.length; u++) {
    if (Math.abs(bytes) >= base) {
      bytes /= base
      magnitude++
    } else {
      break
    }
  }

  // Bytes should be shown without decimals
  if (magnitude === 0) {
    decimals = 0
  }

  const formattedNumber = bytes.toLocaleString(locale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })

  return `${formattedNumber}${narrowNonBreakingSpace}${fileSizeUnits[magnitude]}`
}
