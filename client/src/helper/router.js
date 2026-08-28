import Vue from 'vue'
import VueRouter from 'vue-router'
import axios from 'axios'

Vue.use(VueRouter)

// Lazy-load the App component
const DefaultLayout = () => import('@/layouts/Default.vue')

const routes = [
  {
    path: '/',
    name: 'layout',
    component: DefaultLayout,
    children: [
      {
        path: '*',
        component: () => import('@/Catchall.vue'),
      },
    ],
    props: (route) => ({
      path: route.path,
    }),
  },
]

/**
 * Query parameters that only address a position inside an already rendered page
 * instead of selecting different content, i.e. the legacy equivalent of a `#hash`:
 * `pid` links to a post of the open forum thread (Thread.vue), `showPost` to a post
 * of a wall on the open page (Wall.vue). Both components react to them at runtime.
 */
const anchorQueryParams = ['pid', 'showPost']

/**
 * Window event dispatched when a link to the url that is already open was
 * clicked. The router drops such a navigation as a duplicate, so a page that
 * scrolls to a deep link (see Thread.vue) would not notice the repeated click.
 */
export const sameRouteNavigationEvent = 'fs:same-route-navigation'

/**
 * True if `to` and `from` are the same page and differ only in the anchor
 * parameters above. Such a navigation must not refetch and replace the content:
 * that would remount the page and throw away its scroll position, while the page
 * itself already reacts to the changed parameter (see Thread.vue).
 */
function isSamePageAnchor (to, from) {
  if (!from?.matched.length || to.path !== from.path) return false

  const withoutAnchors = (query) => Object.fromEntries(
    Object.entries(query).filter(([key]) => !anchorQueryParams.includes(key)),
  )
  const toQuery = withoutAnchors(to.query)
  const fromQuery = withoutAnchors(from.query)

  return Object.keys(toQuery).length === Object.keys(fromQuery).length &&
    Object.keys(toQuery).every(key => String(toQuery[key]) === String(fromQuery[key]))
}

function notifySameRouteNavigation (fullPath) {
  window.dispatchEvent(new CustomEvent(sameRouteNavigationEvent, { detail: { fullPath } }))
}

const router = new VueRouter({
  mode: 'history', // Use HTML5 History API
  routes,
  scrollBehavior (to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else if (isSamePageAnchor(to, from)) {
      // Keep the current position, the page scrolls to the linked element itself.
      return false
    } else {
      return { x: 0, y: 0 }
    }
  },
})

/**
 * Navigate to an internal url. The router drops a navigation to the url that is already
 * open, so in that case the open page is notified directly (see `sameRouteNavigationEvent`)
 * instead: clicking a link or a bell notification that points at the current url should
 * still re-evaluate the deep link and refresh the content of the open page.
 */
export function navigate (to) {
  const notifySameRoute = () => notifySameRouteNavigation(to)

  if (to === router.currentRoute.fullPath) {
    notifySameRoute()
    return Promise.resolve()
  }

  return router.push(to).catch(error => {
    // The url is written differently (e.g. another order of the query parameters) but
    // resolves to the route that is already open, so the router rejects the navigation.
    if (error?.name !== 'NavigationDuplicated') throw error
    notifySameRoute()
  })
}

// Flag to track the initial page load to prevent reload loops
let isFirstNavigation = true

// Navigation guard to fetch content for each route
router.beforeEach(async (to, from, next) => {
  const isInitial = isFirstNavigation
  isFirstNavigation = false

  // Do not intercept API requests or auth/registration routes
  if (
    to.path.startsWith('/api/') ||
    to.path === '/logout'
  ) {
    if (window.location.pathname !== to.fullPath) {
      window.location.href = to.fullPath
    }
    return
  }

  // Dynamically set route meta for elements that should be hidden
  const pathPrefix = to.path.split('/')[1]
  to.meta.hideFooter = ['msg', 'karte'].includes(pathPrefix)

  // Deep link into the page that is already open: keep the rendered content as
  // it is, only the url changes so the page can scroll to the linked element.
  if (isSamePageAnchor(to, from)) {
    to.meta.content = from.meta.content
    next()
    return
  }

  // Use pre-rendered content for the initial page load
  const initialContentTemplate = document.getElementById('initial-content')
  if (initialContentTemplate && !window.__initialContentConsumed && window.location.pathname === to.path) {
    window.__initialContentConsumed = true
    to.meta.content = initialContentTemplate.innerHTML
    next()
    return
  }

  try {
    // Fetch the content-only version of the page
    const response = await axios.get(to.fullPath, {
      headers: {
        'X-Content-Only': '1', // Request content-only layout
        'X-Requested-With': 'XMLHttpRequest', // Mark as AJAX request
      },
    })

    // Check if the response URL indicates a redirect occurred
    const finalUrl = response.request?.responseURL
    if (finalUrl) {
      const currentOrigin = window.location.origin
      if (finalUrl.startsWith(currentOrigin)) {
        const finalPath = finalUrl.substring(currentOrigin.length) || '/'
        const cleanFinalPath = finalPath.split('?')[0]
        const cleanToPath = to.path

        if (cleanFinalPath !== cleanToPath) {
          next(finalPath)
          return
        }
      }
    }

    // Store the HTML content in the route's meta
    to.meta.content = response.data

    next()
  } catch (error) {
    console.error('Failed to fetch route content:', error)

    if (!isInitial) {
      // It's a page-to-page navigation failure, reload the page
      window.location.href = to.fullPath
      return
    }

    // If it fails on initial load, clear stale content and proceed
    to.meta.content = ''
    next()
  }
})

// Manage dynamic page elements and body classes after navigation
router.afterEach((to, from) => {
  const getPageClass = (path) => {
    // Extract the first segment of the path, defaulting to 'index'
    const segment = path.split('/')[1]
    return `page-${segment || 'index'}`
  }

  // Remove old page classes
  if (from) {
    const oldClass = getPageClass(from.path)
    document.documentElement.classList.remove(oldClass)
    document.body.classList.remove(oldClass)
  }

  // Add new page classes
  const newClass = getPageClass(to.path)
  document.documentElement.classList.add(newClass)
  document.body.classList.add(newClass)
})

const originalPush = VueRouter.prototype.push
VueRouter.prototype.push = function (location, onResolve, onReject) {
  // A link to the url that is already open is dropped by the router. Without this,
  // nothing at all happens on such a click, not even for the page that is open
  // (see `sameRouteNavigationEvent`). router-link passes callbacks, navigate() and
  // own calls do not, so both ways have to be covered.
  const handleDuplicate = (error) => {
    const isDuplicate = error?.name === 'NavigationDuplicated'
    if (isDuplicate) notifySameRouteNavigation(this.currentRoute.fullPath)
    return isDuplicate
  }

  if (onResolve || onReject) {
    return originalPush.call(this, location, onResolve, (error) => {
      handleDuplicate(error)
      if (onReject) onReject(error)
    })
  }

  return originalPush.call(this, location).catch(error => {
    if (!handleDuplicate(error)) throw error
  })
}

export default router
