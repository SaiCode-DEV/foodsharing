import serverData from '@/helper/server-data'
import de from '@translations/messages.de.yml'
import en from '@translations/messages.en.yml'
import es from '@translations/messages.es.yml'
import fr from '@translations/messages.fr.yml'
import it from '@translations/messages.it.yml'
import nbNo from '@translations/messages.nb_NO.yml'
import tr from '@translations/messages.tr.yml'
import { captureError } from '@/sentry'

export const { locale } = serverData

export default function (path, variables = {}) {
  // find the selected language, use German as fallback
  const language = { en: en, es: es, fr: fr, it: it, nb_NO: nbNo, tr: tr }
  const selected = Object.keys(language).find(l => l.localeCompare(locale || l) === 0)
  const src = selected ? language[selected] : de

  // https://youmightnotneed.com/lodash#get
  const pathArray = Array.isArray(path) ? path : path.match(/([^[.\]])+/g)
  let result = pathArray.reduce((prevObj, key) => prevObj && prevObj[key], src)

  // https://youmightnotneed.com/lodash#get
  if (!result) {
    result = pathArray.reduce((prevObj, key) => prevObj && prevObj[key], de)
  }
  if (!result) {
    captureError(`Missing translation for [${path}]`)
    return path
  }
  if (typeof result === 'object') {
    captureError(`Translation for [${path}] has sub Translations: [${JSON.stringify(result)}]`)
    return path
  }
  if (typeof result !== 'string') {
    captureError(`Translation for [${path}] is not a string: ${typeof result} [${result}]`)
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
