export function useEnvironmentCheck () {
  const isTest = window.location.port === '8080'
  const isDev = window.location.hostname.includes('localhost') || window.location.hostname.includes('gitpod')
  const isBeta = window.location.hostname.includes('beta.foodsharing')
  const isDotAt = window.location.hostname.includes('foodsharing.at')
  const isChrome = navigator.userAgent.includes('Chrome')
  const isSafari = !isChrome && navigator.userAgent.includes('Safari')

  return {
    isTest,
    isDev,
    isBeta,
    isDotAt,
    isChrome,
    isSafari,
  }
}
