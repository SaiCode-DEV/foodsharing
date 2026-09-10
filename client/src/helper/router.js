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
const anchorQueryParams = ['pid', 'showPost', 'cid']

/**
 * Window event dispatched after content for the url that is already open has
 * been fetched again. Components that handle deep links (see Thread.vue) can
 * also use it to re-evaluate the repeated link.
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

let routeRefreshPromise = null

function notifySameRouteNavigation (fullPath, content) {
  window.dispatchEvent(new CustomEvent(sameRouteNavigationEvent, {
    detail: { fullPath, content },
  }))
}

function redirectPath (response, requestedPath) {
  const finalUrl = response.request?.responseURL
  const currentOrigin = window.location.origin

  if (!finalUrl?.startsWith(currentOrigin)) return null

  const finalPath = finalUrl.substring(currentOrigin.length) || '/'
  return finalPath.split('?')[0] === requestedPath.split('?')[0] ? null : finalPath
}

async function fetchRouteContent (fullPath) {
  const response = await axios.get(fullPath, {
    headers: {
      'X-Content-Only': '1',
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  return {
    content: response.data,
    redirectedTo: redirectPath(response, fullPath),
  }
}

/**
 * Fetch and remount the current page when its link is clicked again. This lives
 * in the router so every page gets the same refresh behaviour without having to
 * implement it in its own root component.
 */
function refreshCurrentRoute (fullPath) {
  if (routeRefreshPromise) return routeRefreshPromise

  window.showLoading?.()
  routeRefreshPromise = fetchRouteContent(fullPath).then(({ content, redirectedTo }) => {
    if (redirectedTo) return router.push(redirectedTo)

    notifySameRouteNavigation(fullPath, content)
  }).catch(error => {
    console.error('Failed to refresh route content:', error)
    window.location.href = fullPath
  }).finally(() => {
    window.hideLoading?.()
    routeRefreshPromise = null
  })

  return routeRefreshPromise
}

const originalPush = router.push.bind(router)
router.push = function (location, onResolve, onReject) {
  const handleDuplicate = (error) => {
    if (error?.name !== 'NavigationDuplicated') return false

    refreshCurrentRoute(this.currentRoute.fullPath)
    return true
  }

  // RouterLink uses callbacks, while direct calls return a promise.
  if (onResolve || onReject) {
    return originalPush(location, onResolve, error => {
      if (!handleDuplicate(error)) onReject?.(error)
    })
  }

  return originalPush(location).catch(error => {
    if (!handleDuplicate(error)) throw error
    return routeRefreshPromise
  })
}

/**
 * Navigate to an internal url. The router drops a navigation to the url that is already
 * open, so in that case fetch and remount its content instead. This also emits
 * `sameRouteNavigationEvent` so deep-link components can re-evaluate the link.
 */
export function navigate (to) {
  if (to === router.currentRoute.fullPath) {
    return refreshCurrentRoute(to)
  }

  return router.push(to)
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
    const { content, redirectedTo } = await fetchRouteContent(to.fullPath)

    // Check if the response URL indicates a redirect occurred
    if (redirectedTo) {
      next(redirectedTo)
      return
    }

    // Store the HTML content in the route's meta
    to.meta.content = content

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

export default router
