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

const router = new VueRouter({
  mode: 'history', // Use HTML5 History API
  routes,
  scrollBehavior (to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { x: 0, y: 0 }
    }
  },
})

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

export default router
