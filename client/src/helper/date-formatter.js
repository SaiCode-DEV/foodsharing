import serverData from './server-data'
import RelativeTimeFormat from 'relative-time-format'

// relative-time-format localises only the locales explicitly registered (unlike Intl's
// toLocaleString, which localises itself). Register every language we ship translations
// for, so relative times ("vor 10 Sekunden", "Heute") localise instead of falling back
// to English (#2675). Keep this list in sync with the locales in /translations.
import ar from 'relative-time-format/locale/ar'
import cs from 'relative-time-format/locale/cs'
import da from 'relative-time-format/locale/da'
import de from 'relative-time-format/locale/de'
import el from 'relative-time-format/locale/el'
import en from 'relative-time-format/locale/en'
import es from 'relative-time-format/locale/es'
import fr from 'relative-time-format/locale/fr'
import it from 'relative-time-format/locale/it'
import lb from 'relative-time-format/locale/lb'
import lt from 'relative-time-format/locale/lt'
import nb from 'relative-time-format/locale/nb'
import nl from 'relative-time-format/locale/nl'
import pl from 'relative-time-format/locale/pl'
import pt from 'relative-time-format/locale/pt'
import ru from 'relative-time-format/locale/ru'
import ta from 'relative-time-format/locale/ta'
import tr from 'relative-time-format/locale/tr'
import uk from 'relative-time-format/locale/uk'
import zh from 'relative-time-format/locale/zh'

const SUPPORTED_RTF_LOCALES = [ar, cs, da, de, el, en, es, fr, it, lb, lt, nb, nl, pl, pt, ru, ta, tr, uk, zh]
SUPPORTED_RTF_LOCALES.forEach((l) => RelativeTimeFormat.addLocale(l))

const locale = serverData.locale

/**
 * Returns the difference between two date, in specific unit
 * @param {Date} dateA
 * @param {Date} dateB
 * @param {second|minute|hour|day|week|month|year} unit
 * @returns
 */
function differenceFromDatesToUnit (dateA, dateB, unit) {
  const isLeapYear = [dateA, dateB].some(date => date.getFullYear() % 4 === 0)
  const diffInMs = dateA - dateB

  switch (unit) {
    case 'second':
      return diffInMs / 1_000
    case 'minute':
      return diffInMs / 60_000
    case 'hour':
      return diffInMs / 3_600_000
    case 'day':
      return diffInMs / 86_400_000
    case 'week':
      return diffInMs / 604_800_000
    case 'month':
      return diffInMs / 2_629_800_000
    case 'year':
      if (isLeapYear) {
        return diffInMs / 31_622_400_000
      } else {
        return diffInMs / 31_536_000_000
      }
  }
}

export default {
  /**
   *
   * @param {Number} seconds amount of seconds
   * @returns {Number} milliseconds
   */
  SecondsToMs (seconds = 1) {
    return seconds * 1000
  },

  /**
   *
   * @param {Number} minutes amount of minutes
   * @returns {Number} milliseconds
   */
  MinutesToMs (minutes = 1) {
    return this.SecondsToMs(minutes * 60)
  },

  /**
   *
   * @param {Number} hours amount of hours
   * @returns {Number} milliseconds
   */
  HoursToMs (hours = 1) {
    return this.MinutesToMs(hours * 60)
  },

  /**
   *
   * @param {Number} days amount of days
   * @returns {Number} milliseconds
   */
  DaysToMs (days = 1) {
    return this.HoursToMs(days * 24)
  },

  /**
   *
   * @param {Number} weeks amount of weeks
   * @returns {Number} milliseconds
   */
  WeeksToMs (weeks = 1) {
    return this.DaysToMs(weeks * 7)
  },

  /**
   *
   * @param {Number} months amount of months
   * @returns {Number} milliseconds
   */
  MonthsToMs (months = 1) {
    return this.WeeksToMs(months * 4)
  },

  /**
   *
   * @param {Number} years amount of years
   * @returns {Number} milliseconds
   */
  YearsToMs (years = 1) {
    return years * 31556952000
  },

  /**
   *
   * @param {Date} date amount of years
   * @param {DateTimeFormatOptions} options to style the date
   * @param {String|undefined} options.localeMatcher "best fit" | "lookup" | undefined;
   * @param {String|undefined} options.weekday "long" | "short" | "narrow" | undefined;
   * @param {String|undefined} options.era "long" | "short" | "narrow" | undefined;
   * @param {String|undefined} options.year "numeric" | "2-digit" | undefined;
   * @param {String|undefined} options.month "numeric" | "2-digit" | "long" | "short" | "narrow" | undefined;
   * @param {String|undefined} options.day "numeric" | "2-digit" | undefined;
   * @param {String|undefined} options.hour "numeric" | "2-digit" | undefined;
   * @param {String|undefined} options.minute "numeric" | "2-digit" | undefined;
   * @param {String|undefined} options.second "numeric" | "2-digit" | undefined;
   * @param {String|undefined} options.timeZoneName "short" | "long" | "shortOffset" | "longOffset" | "shortGeneric" | "longGeneric" | undefined;
   * @param {String|undefined} options.formatMatcher "best fit" | "basic" | undefined;
   * @param {Boolean|undefined} options.hour12 boolean | undefined;
   * @param {String|undefined} options.timeZone string | undefined;
   * @returns {String} formatted date
   */
  format (date = new Date(), options = {}) {
    return new Date(date).toLocaleString(locale, options)
  },

  /**
   * Checks if the date is the same year
   * @param {Date} date
   * @returns {Boolean} true if date is year
   */
  isSameYear (date = new Date()) {
    return new Date().getFullYear() === new Date(date).getFullYear()
  },

  /**
   * Checks if the date is in the past
   * @param {Date} date
   * @returns {Boolean} true if date is in the past
   */
  isPast (date = new Date()) {
    return new Date(date) < new Date()
  },

  /**
   * Compares to DateStrings and returns true if they are equal
   * @param {Date} date
   * @param {Date} otherDate
   * @returns {Boolean} true if date is the same
   */
  isSame (date = new Date(), otherDate = new Date()) {
    return new Date(otherDate).toLocaleDateString() === new Date(date).toLocaleDateString()
  },

  /**
   * Checks if the date is today
   * @param {Date} date
   * @returns {Boolean} true if date is today
   */
  isToday (date = new Date()) {
    return new Date().toLocaleDateString() === new Date(date).toLocaleDateString()
  },

  /**
   * Check if the date is tomorrow
   * @param {Date} date
   * @returns {Boolean} true if date is tomorrow
   */
  isTomorrow (date = new Date()) {
    const tomorrow = new Date()
    tomorrow.setDate(tomorrow.getDate() + 1)
    return tomorrow.toLocaleDateString() === new Date(date).toLocaleDateString()
  },

  /**
   * Check if an date is soon by a given number of hours
   * @param {Date} date
   * @param {Number} duration duration in hours
   * @returns {Boolean} true if date is in the given duration
   */
  isSoonInHours (date = new Date(), duration = 3) {
    return this.getDifferenceToNowInHours(date) < duration
  },

  /**
   * Calcucates the difference between two dates in hours
   * @param {Date} date
   * @returns {Number} difference between date and now in hours
   */
  getDifferenceToNowInHours (date = new Date()) {
    return Math.trunc((new Date(date) - new Date()) / this.HoursToMs())
  },

  /**
   * Calcucates the difference between two dates in days
   * @param {Date} date
   * @returns {Number} difference between date and now in days
   */
  getDifferenceToNowInDays (date = new Date()) {
    return Math.trunc((new Date(date) - new Date()) / this.DaysToMs())
  },

  /**
   * Calcucates the difference between two dates in months
   * @param {Date} date
   * @returns {Number} difference between now and a date in months
   */
  getDifferenceToNowInMonths (date = new Date()) {
    return Math.trunc((new Date() - new Date(date)) / this.MonthsToMs())
  },

  /**
   * Calcucates the difference between two dates in years
   * @param {Date} date
   * @returns {Number} difference between date and now in years
   */
  getDifferenceToNowInYears (date = new Date()) {
    return Math.trunc((new Date() - new Date(date)) / this.YearsToMs())
  },

  /**
   * Shows the date in a human readable relative time format
   * - `vor 10 sekunden` based on the users locale
   *
   * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/RelativeTimeFormat/RelativeTimeFormat
   *
   * @param {Date} date a date to format
   * @param {Object} options options for the formatter
   * @param {Boolean} options.short to show the relative time in short format (e.g. '10 s ago' in stead of '10 seconds ago')
   * @param {Boolean} dateOnly to specify that the given date contains no time information (otherwise 0h:0m:0s will be assumed)
   * @returns {string} the relative time
   */
  relativeTime (date = new Date(), { short = false } = {}, dateOnly = false) {
    const rtf = new RelativeTimeFormat(locale, {
      localeMatcher: 'best fit',
      numeric: 'auto',
      style: short ? 'narrow' : 'long',
    })

    let dateA = new Date()
    let dateB = new Date(date)

    const isInFuture = dateA - dateB < 0

    if (isInFuture) {
      dateA = new Date(date)
      dateB = new Date()
    }

    if (dateOnly && differenceFromDatesToUnit(dateA, dateB, 'day') < 1) {
      // Special case to prevent something like '16 hours ago' for two datetimes from the same day
      // if one of the datetimes contains no time information and is interpreted as midnight.
      return rtf.format(0, 'day')
    }

    const { value = 0, unit = 'second' } = ['year', 'month', 'week', 'day', 'hour', 'minute', 'second']
      .map((unit) => {
        const value = Math.floor(differenceFromDatesToUnit(dateA, dateB, unit))
        const difference = (dateA - dateB) / value

        if (difference >= 1) {
          return { value, unit }
        }

        return null
      })
      .filter(e => e !== null)
      .find(e => e.value >= 1) || {}

    return rtf.format(isInFuture ? value : -value, unit)
  },

  /**
   * Shows the date in a human readable format
   * - `Heute, 5.Juli, 13:37` based on the users locale
   *
   * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toLocaleDateString
   *
   * @param {Date} date a date to format
   * @param {Boolean} options.weekday whether to include the weekday in the result
   * @returns {string} the formated date with time
   */
  dateTime (date = new Date(), { weekday = true } = {}) {
    const d = new Date(date)
    const options = {
      weekday: weekday && !this.isToday(d) ? 'long' : undefined,
      year: !this.isSameYear(d) ? 'numeric' : undefined,
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }
    if (this.isToday(d) && weekday) {
      const rtf = new RelativeTimeFormat(locale, { numeric: 'auto' })
      return `${toCapitalize(rtf.format(0, 'day'))}, ${d.toLocaleString(locale, options)}`
    }

    return d.toLocaleString(locale, options)
  },

  /**
   * Shows the date in a human readable realtive time format
   * - `vor 10 sekunden` based on the users locale if trigger for releative time is true & from the same year
   * - `Heute, 5.Juli, 13:37`  based on the users locale if trigger for releative time is false & from the same year
   * - `vor 1 Jahr (Sonntag, 5.Juli. 2020, 13:37)` based on the users locale if the date is not the same year
   *
   * @param {Date} date a date to format
   * @param {Object} options options for the formatter
   * @param {Boolean} options.isRelativeTime force to use relative time otherwise it uses same day as trigger
   * @param {Boolean} options.short to show the relative time in short format (e.g. '10 s ago' in stead of '10 seconds ago')
   * @param {Boolean} options.weekday whether to include the weekday in the result
   * @returns {string} the relative time
   */
  base (date = new Date(), { isRelativeTime = this.isToday(date), short = false, weekday = true } = {}) {
    const d = new Date(date)
    if (this.isSameYear(d)) {
      if (isRelativeTime) {
        return this.relativeTime(d, { short })
      } else {
        return this.dateTime(d, { weekday })
      }
    } else {
      return this.relativeTime(d) + ` (${this.dateTime(d)})`
    }
  },

  /**
   * Is a special tooltip date, which is only returing when a parameter is set
   * - `Heute, 05. Juli, 13:37` based on the users locale
   *
   * @param {Date} date a date to format
   * @param {Object} options options for the formatter
   * @param {Boolean} options.isShown a trigger to get the dateTime string or null
   * @returns {string|null} the date time or null if the date is null
   */
  dateTimeTooltip (date = new Date(), { isShown = this.isToday(date) } = {}) {
    if (isShown) {
      return this.dateTime(date)
    }
  },

  /**
   * Shows the date in a human readable format
   * - `Heute, 5.7.` based on the users locale if date is today
   * - `5.7.` based on the users locale if date is today and short is true
   * - `Sonntag, 5.7.2020` based on the users locale if the date is not the same year
   *
   * @param {Date} date a date to format
   * @param {Object} options options for the formatter
   * @param {full|no-weekday|normal} options.type a trigger to remove the weekday
   * @returns {string} the date or totay
   */
  date (date = new Date(), { short = false, type = 'default' } = {}) {
    const d = new Date(date)
    let options

    if (short) {
      type = 'no-weekday'
    }

    switch (type) {
      case 'full': {
        options = {
          weekday: !this.isToday(d) ? 'long' : undefined,
          year: !this.isSameYear(d) ? 'numeric' : undefined,
          month: 'long',
          day: 'numeric',
        }
        break
      }
      case 'no-weekday': {
        options = {
          weekday: undefined,
          year: !this.isSameYear(d) ? 'numeric' : undefined,
          month: 'long',
          day: 'numeric',
        }
        break
      }
      default: {
        options = {
          weekday: !this.isToday(d) ? 'long' : undefined,
          year: !this.isSameYear(d) ? 'numeric' : undefined,
          month: 'numeric',
          day: 'numeric',
        }
        break
      }
    }

    if (this.isToday(d)) {
      const rtf = new RelativeTimeFormat(locale, { numeric: 'auto' })
      return `${toCapitalize(rtf.format(0, 'day'))}, ${d.toLocaleDateString(locale, options)}`
    }

    return d.toLocaleString(locale, options)
  },

  /**
   * Shows the time in a human readable format
   * - `00:00` or `00:00:00`
   * - based on the users locale
   *
   * @param {Date} date a date to format
   * @param {Object} options options for the formatter
   * @param {Boolean} options.showSeconds whether to include seconds in the result
   * @returns {string|null} the date time or null if the date is null
   */
  time (date = new Date(), { showSeconds = false } = {}) {
    const options = {
      hour: '2-digit',
      minute: '2-digit',
      second: showSeconds ? '2-digit' : undefined,
    }
    return new Date(date).toLocaleTimeString(locale, options)
  },

  /**
   * Shows the date in a human readable format
   * - `05.07.2022` based on the users locale
   *
   * @param {Date} date a date to format
   * @returns {string|null} the date time or null if the date is null
   */
  dateBasic (date = new Date()) {
    const options = {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }
    return new Date(date).toLocaleDateString(locale, options)
  },
}

/**
 * @param {String} str
 * @returns First letter of the string in capital
 */
function toCapitalize (str) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

/**
 *
 * Wrong Time sending?
 *
 * @param {*} date
 * @returns
 */
export function toISOStringWithTimezone (date) {
  const tzOffset = -date.getTimezoneOffset()
  const diff = tzOffset >= 0 ? '+' : '-'
  const pad = n => `${Math.floor(Math.abs(n))}`.padStart(2, '0')
  return date.getFullYear() +
    '-' + pad(date.getMonth() + 1) +
    '-' + pad(date.getDate()) +
    'T' + pad(date.getHours()) +
    ':' + pad(date.getMinutes()) +
    ':' + pad(date.getSeconds()) +
    diff + pad(tzOffset / 60) +
    ':' + pad(tzOffset % 60)
}
