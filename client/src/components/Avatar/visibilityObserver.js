export let visibilityObserver = null
if (typeof IntersectionObserver !== 'undefined') {
  visibilityObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        visibilityObserver.intersectionCallbacks.get(entry.target)?.()
        visibilityObserver.unobserve(entry.target)
        visibilityObserver.intersectionCallbacks.delete(entry.target)
      }
    })
  }, { rootMargin: '300px' })
  visibilityObserver.intersectionCallbacks = new Map()
}

export function lazyLoad (target, callback) {
  if (!visibilityObserver) {
    callback()
    return
  }
  visibilityObserver.intersectionCallbacks.set(target, callback)
  visibilityObserver.observe(target)
}
