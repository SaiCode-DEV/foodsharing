import Vue from 'vue'
import serverData, { isDev, isTest } from '@/helper/server-data'
import { captureError } from '@/sentry'
import VueI18n from 'vue-i18n'
import { createI18n } from 'vue-i18n-bridge'

export const { locale } = serverData

const DEFAULT_LOCALE = 'de'

// Discover available locales from translations folder (Webpack)
const ctx = require.context('@translations', false, /^\.\/messages\.[^/]+\.yml$/)
const AVAILABLE = ctx.keys().map(k => {
  const m = k.match(/messages\.(.+)\.yml$/)
  return m ? m[1] : null
}).filter(Boolean)

function hasLocale (lang) {
  return AVAILABLE.includes(lang)
}

function languagePart (lang) {
  return lang && lang.includes('_') ? lang.substring(0, lang.indexOf('_')) : lang
}

function bestLocale (requested) {
  if (requested && hasLocale(requested)) return requested
  const macro = languagePart(requested)
  if (macro && hasLocale(macro)) return macro
  return DEFAULT_LOCALE
}

// vue-i18n (v9 message compiler, via the bridge) treats '@' as the linked-message
// delimiter, so a literal '@' that is not valid linked syntax (e.g. the gender-neutral
// Spanish "segur@" or an email address) throws "Invalid linked format" at translation
// time (see #2718). Our translations never use linked messages, so escape every '@' to
// the v9 literal "{'@'}" when messages are loaded. Doing this at the load layer keeps the
// Weblate-managed source files untouched.
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

function loadMessages (lang) {
  try {
    return escapeLinkedTokens(ctx(`./messages.${lang}.yml`))
  } catch (e) {
    return null
  }
}

// Install legacy bridge
Vue.use(VueI18n, { bridge: true })

const initialRequested = locale || DEFAULT_LOCALE
const initialLocale = bestLocale(initialRequested)

const baseMessages = {}
const deMessages = loadMessages(DEFAULT_LOCALE)
if (deMessages) baseMessages[DEFAULT_LOCALE] = deMessages
const initialMessages = loadMessages(initialLocale)
if (initialMessages && initialLocale !== DEFAULT_LOCALE) {
  baseMessages[initialLocale] = initialMessages
}

const datetimeFormats = {
  en: {
    short: {
      year: 'numeric', month: 'short', day: 'numeric',
    },
    long: {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      weekday: 'short',
      hour: 'numeric',
      minute: 'numeric',
    },
    shortDateTime: {
      year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric',
    },
  },
  de: {
    short: {
      year: 'numeric', month: 'short', day: 'numeric',
    },
    long: {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      weekday: 'short',
      hour: 'numeric',
      minute: 'numeric',
    },
    shortDateTime: {
      year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric',
    },
  },
}
// fallback datetime formats for other languages
AVAILABLE.forEach((lang) => {
  if (!datetimeFormats[lang]) {
    datetimeFormats[lang] = datetimeFormats[DEFAULT_LOCALE]
  }
})

export const i18nInstance = createI18n({
  // Legacy API so we can use $t and options API across Vue 2 app
  legacy: true,
  locale: initialLocale,
  fallbackLocale: DEFAULT_LOCALE,
  messages: baseMessages,
  datetimeFormats,
  // We intentionally store some HTML in translations; keep legacy behavior
  warnHtmlMessage: false,
  escapeParameterHtml: false,
  missing (loc, key) {
    captureError(`Missing translation for ${loc}: [${key}]`)
    if (isDev || isTest) {
      throw new Error(`Missing translation for ${loc}: [${key}]`)
    }
    return key
  },
}, VueI18n)

// Helper to dynamically switch locale later if needed
export function setLocale (lang) {
  const target = bestLocale(lang)
  if (!i18nInstance.global.getLocaleMessage(target) ||
      Object.keys(i18nInstance.global.getLocaleMessage(target) || {}).length === 0) {
    const msgs = loadMessages(target)
    if (msgs) i18nInstance.global.setLocaleMessage(target, msgs)
  }
  i18nInstance.global.locale = target
}

// Backwards-compatible default export matching previous API
export default function t (path, variables = {}) {
  if (!path) {
    captureError(`Invalid path using ${i18nInstance.global.locale}: ${path}`)
    return path
  }
  try {
    return i18nInstance.global.t(path, variables)
  } catch (e) {
    captureError(`Translation error for ${i18nInstance.global.locale}: [${path}] -> ${e && e.message}`)
    return path
  }
}

/**
 * Returns only the macro language code part of the user's locale. For example, returns 'nb' if the locale is 'nb_NO'.
 */
export function languageCodeISO () {
  return languagePart(locale)
}
