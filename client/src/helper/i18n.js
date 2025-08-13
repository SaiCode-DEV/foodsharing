import serverData from '@/helper/server-data'
import { captureError } from '@/sentry'

export const { locale } = serverData

const DEFAULT_LOCALE = 'de'
const ALL_LOCALES = ['en', 'es', 'fr', 'it', 'nb_NO', 'ta', 'tr']
const cachedLanguages = {}

/**
 * Makes sure that the language is in the cached languages. Fetches it from the server if necessary.
 */
function requireLanguage (lang) {
  if (!(lang in cachedLanguages)) {
    cachedLanguages[lang] = require(`@translations/messages.${lang}.yml`)
  }
  return cachedLanguages[lang]
}

export default function (path, variables = {}) {
  // fetch German as fallback
  if (!(DEFAULT_LOCALE in cachedLanguages)) {
    requireLanguage(DEFAULT_LOCALE)
  }

  // find the selected language
  const selected = ALL_LOCALES.find(l => l.localeCompare(locale || l) === 0)
  const src = requireLanguage(selected || DEFAULT_LOCALE)
  if (!path) {
    captureError(`Invalid path using ${locale}: ${path}`)
    return path
  }

  const pathArray = Array.isArray(path) ? path : path.match(/([^[.\]])+/g)
  let result = pathArray.reduce((prevObj, key) => prevObj && prevObj[key], src)

  if (!result) {
    result = pathArray.reduce((prevObj, key) => prevObj && prevObj[key], cachedLanguages[DEFAULT_LOCALE])
  }
  if (!result) {
    captureError(`Missing translation for ${locale}: [${path}]`)
    return path
  }
  if (typeof result === 'object') {
    captureError(`Translation for ${locale}: [${path}] has sub Translations: [${JSON.stringify(result)}]`)
    return path
  }
  if (typeof result !== 'string') {
    captureError(`Translation for ${locale}: [${path}] is not a string: ${typeof result} [${result}]`)
    return path
  }
  return result.replace(/{([^}]+)}/g, (_, name) => {
    if (Object.prototype.hasOwnProperty.call(variables, name)) {
      return variables[name]
    } else {
      throw captureError(`Variable [${name}] was not provided for [${path}]`)
    }
  })
}

/**
 * Returns only the macro language code part of the user's locale. For example, returns 'nb' if the locale is 'nb_NO'.
 */
export function languageCodeISO () {
  return locale.includes('_') ? locale.substring(0, locale.indexOf('_')) : locale
}
