export default {
  data () {
    return {
      isTest: null,
      isDev: null,
      isBeta: null,
      isDotAt: null,
      isChrome: null,
      isSafari: null,
    }
  },
  created: function () {
    this.isTest = window.location.port === '8080'
    this.isDev = window.location.hostname.includes('localhost')
    this.isBeta = window.location.hostname.includes('beta.foodsharing')
    this.isDotAt = window.location.hostname.includes('foodsharing.at')
    this.isChrome = navigator.userAgent.includes('Chrome')
    this.isSafari = !this.isChrome && navigator.userAgent.includes('Safari')
  },
}
