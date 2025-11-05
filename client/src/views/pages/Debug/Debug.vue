<template>
  <div>
    <h1>{{ i18n('debug.title') }}</h1>
    <b-alert show variant="warning">
      {{ i18n('debug.info') }}
    </b-alert>
    <b-card class="mb-3">
      <b-card-header
        header-tag="header"
        class="p-2 d-flex align-items-center justify-content-between"
        role="tab"
      >
        <h4>Browser Info</h4>
        <div
          @click="isExpanded = !isExpanded"
        >
          <i
            :alt="isExpanded ? i18n('globals.show_more') : i18n('globals.show_less')"
            class="fas fa-angle-down ml-2 animate-rotate"
            :class="{ 'fa-rotate-180': isExpanded }"
          />
        </div>
      </b-card-header>
      <b-collapse
        id="accordion-info"
        v-model="isExpanded"
        visible
        accordion="debug-accordion"
        role="tabpanel"
      >
        <b-card-text>
          <h5>Screen Object</h5>
          <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <td>Screen Resolution</td>
                <td>{{ screenInfo }}</td>
              </tr>
            </tbody>
          </table>

          <h5>Date/Time</h5>
          <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <td>System Time</td>
                <td>{{ new Date().toString() }}</td>
              </tr>
              <tr>
                <td>toLocaleString</td>
                <td>{{ new Date().toLocaleString() }}</td>
              </tr>
            </tbody>
          </table>

          <h5>Internationalization API</h5>
          <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <td>DateTimeFormat</td>
                <td>{{ i18nDateTimeFormat }}</td>
              </tr>
              <tr>
                <td>hourCycle</td>
                <td>{{ i18nResolvedOptions.hourCycle || 'undefined' }}</td>
              </tr>
              <tr>
                <td>locale</td>
                <td>{{ i18nResolvedOptions.locale || 'undefined' }}</td>
              </tr>
              <tr>
                <td>calendar</td>
                <td>{{ i18nResolvedOptions.calendar || 'undefined' }}</td>
              </tr>
              <tr>
                <td>numberingSystem</td>
                <td>{{ i18nResolvedOptions.numberingSystem || 'undefined' }}</td>
              </tr>
              <tr>
                <td>timeZone</td>
                <td>{{ i18nResolvedOptions.timeZone || 'undefined' }}</td>
              </tr>
              <tr>
                <td>year</td>
                <td>{{ i18nResolvedOptions.year || 'undefined' }}</td>
              </tr>
              <tr>
                <td>month</td>
                <td>{{ i18nResolvedOptions.month || 'undefined' }}</td>
              </tr>
              <tr>
                <td>day</td>
                <td>{{ i18nResolvedOptions.day || 'undefined' }}</td>
              </tr>
            </tbody>
          </table>

          <h5>Navigator Object</h5>
          <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <td>userAgent</td>
                <td>{{ navigator.userAgent || 'empty' }}</td>
              </tr>
              <tr>
                <td>appVersion</td>
                <td>{{ navigator.appVersion || 'empty' }}</td>
              </tr>
              <tr>
                <td>appName</td>
                <td>{{ navigator.appName || 'empty' }}</td>
              </tr>
              <tr>
                <td>appCodeName</td>
                <td>{{ navigator.appCodeName || 'empty' }}</td>
              </tr>
              <tr>
                <td>product</td>
                <td>{{ navigator.product || 'empty' }}</td>
              </tr>
              <tr>
                <td>productSub</td>
                <td>{{ navigator.productSub || 'empty' }}</td>
              </tr>
              <tr>
                <td>vendor</td>
                <td>{{ navigator.vendor || 'empty' }}</td>
              </tr>
              <tr>
                <td>vendorSub</td>
                <td>{{ navigator.vendorSub || 'empty' }}</td>
              </tr>
              <tr>
                <td>buildID</td>
                <td>{{ navigator.buildID || 'empty' }}</td>
              </tr>
              <tr>
                <td>platform</td>
                <td>{{ navigator.platform || 'empty' }}</td>
              </tr>
              <tr>
                <td>oscpu</td>
                <td>{{ navigator.oscpu || 'empty' }}</td>
              </tr>
              <tr>
                <td>hardwareConcurrency</td>
                <td>{{ navigator.hardwareConcurrency || 'undefined' }}</td>
              </tr>
              <tr>
                <td>deviceMemory</td>
                <td>{{ navigator.deviceMemory || 'undefined' }}</td>
              </tr>
              <tr>
                <td>devicePosture.type</td>
                <td>{{ devicePostureType }}</td>
              </tr>
              <tr>
                <td>language</td>
                <td>{{ navigator.language || 'empty' }}</td>
              </tr>
              <tr>
                <td>languages</td>
                <td>{{ JSON.stringify(navigator.languages) || 'empty' }}</td>
              </tr>
              <tr>
                <td>onLine</td>
                <td>{{ navigator.onLine }}</td>
              </tr>
              <tr>
                <td>doNotTrack</td>
                <td>{{ navigator.doNotTrack || 'empty' }}</td>
              </tr>
              <tr>
                <td>cookieEnabled</td>
                <td>{{ navigator.cookieEnabled }}</td>
              </tr>
              <tr>
                <td>maxTouchPoints</td>
                <td>{{ navigator.maxTouchPoints || 'empty' }}</td>
              </tr>
              <tr>
                <td>webdriver</td>
                <td>{{ navigator.webdriver }}</td>
              </tr>
              <tr>
                <td>pdfViewerEnabled</td>
                <td>{{ pdfViewerEnabled }}</td>
              </tr>
              <tr>
                <td>globalPrivacyControl</td>
                <td>{{ globalPrivacyControl }}</td>
              </tr>
            </tbody>
          </table>
        </b-card-text>
      </b-collapse>
    </b-card>
    <b-card class="mb-3">
      <b-card-header
        header-tag="header"
        class="p-2 d-flex align-items-center justify-content-between"
        role="tab"
      >
        <h4>Client/Server Info</h4>
        <div
          @click="isExpandedServer = !isExpandedServer"
        >
          <i
            :alt="isExpandedServer ? i18n('globals.show_more') : i18n('globals.show_less')"
            class="fas fa-angle-down ml-2 animate-rotate"
            :class="{ 'fa-rotate-180': isExpandedServer }"
          />
        </div>
      </b-card-header>
      <b-collapse
        id="accordion-info"
        v-model="isExpandedServer"
        visible
        accordion="debug-server-accordion"
        role="tabpanel"
      >
        <b-card-text>
          <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <td>System Time (client)</td>
                <td>{{ serverInfo.clientTime }}</td>
              </tr>
              <tr>
                <td>System Time (server)</td>
                <td>{{ serverInfo.time }}</td>
              </tr>
              <tr>
                <td>Secure context</td>
                <td>server: {{ serverInfo.https }} / client: {{ https }}</td>
              </tr>
            </tbody>
          </table>
        </b-card-text>
      </b-collapse>
    </b-card>

    <b-card class="mb-3" title="Service Workers">
      <b-card-text>
        <span>browser support: {{ 'serviceWorker' in navigator ? '✅ yes' : '❌ no' }}</span>
        <br>
        <span>
          current state:
          {{
            navigator?.serviceWorker?.controller
              ? '✅ registered'
              : '❌ not registered'
          }}
        </span>
      </b-card-text>
      <b-button variant="primary" @click="registerServiceWorker()">
        Register
      </b-button>
      <b-button
        variant="danger"
        class="ml-2"
        @click="resetServiceWorker()"
      >
        Reset
      </b-button>
    </b-card>
    <b-card class="mb-3" title="Cache Storage">
      <b-card-text>
        <div v-if="cacheSupported">
          <div v-if="isLoadingCache">
            Loading cache information...
          </div>
          <div v-else-if="cacheStats.length === 0">
            No caches found
          </div>
          <table v-else class="table table-striped table-sm">
            <thead>
              <tr>
                <th>Cache Name</th>
                <th>Items</th>
                <th>Size</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cache in cacheStats" :key="cache.name">
                <td>{{ cache.name }}</td>
                <td>{{ cache.count }}</td>
                <td>{{ formatSize(cache.size) }}</td>
                <td>
                  <b-button
                    size="sm"
                    variant="danger"
                    @click="clearSingleCache(cache.name)"
                  >
                    Clear
                  </b-button>
                </td>
              </tr>
              <tr v-if="cacheStats.length > 1">
                <td><strong>Total</strong></td>
                <td><strong>{{ totalCacheItems }}</strong></td>
                <td><strong>{{ formatSize(totalCacheSize) }}</strong></td>
                <td />
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else>
          <b-alert show variant="warning">
            Cache API is not supported in this browser
          </b-alert>
        </div>
        <div class="mt-2">
          <b-button
            variant="primary"
            :disabled="isLoadingCache"
            @click="refreshCacheStats"
          >
            <i
              class="fas fa-sync-alt"
              :class="{ 'fa-spin': isLoadingCache }"
            /> Refresh
          </b-button>
          <b-button
            variant="danger"
            class="ml-2"
            :disabled="isLoadingCache || cacheStats.length === 0"
            @click="clearAllCaches"
          >
            Clear All Caches
          </b-button>
        </div>
      </b-card-text>
    </b-card>
  </div>
</template>

<script setup>
import i18n from '@/helper/i18n'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { registerServiceWorker, resetServiceWorker } from '@/registerServiceWorker'

const isExpanded = ref(false)
const isExpandedServer = ref(false)
const navigator = window.navigator
const https = window.location.protocol === 'https:' ? '✅' : '❌'

// Cache Storage
const cacheSupported = ref('caches' in window)
const cacheStats = ref([])
const isLoadingCache = ref(false)

// Server debug info fetched from backend
const serverInfo = ref({ server_time: null, https: false, https_raw: null })

const fetchServerDebug = async () => {
  try {
    const res = await fetch('/api/debug/server')
    const clientTime = new Date().toISOString()
    if (!res.ok) {
      console.warn('Server debug API returned', res.status)
      return
    }
    const data = await res.json()
    data.clientTime = clientTime
    serverInfo.value = data
  } catch (e) {
    console.error('Failed to fetch server debug info', e)
  }
}

// Reactive references for window dimensions
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 0)
const windowHeight = ref(typeof window !== 'undefined' ? window.innerHeight : 0)

// Handle window resize
const handleResize = () => {
  windowWidth.value = window.innerWidth
  windowHeight.value = window.innerHeight
}

// Add and remove event listeners
onMounted(() => {
  window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
})

// Screen information - now depends on reactive refs so it updates on resize
const screenInfo = computed(() => {
  if (typeof window === 'undefined' || !window.screen) return 'Not available'

  const { width, height, colorDepth } = window.screen
  const viewportWidth = windowWidth.value
  const viewportHeight = windowHeight.value
  const aspectRatio = Math.round((width / height) * 10) / 10
  const colorMode = colorDepth ? `${colorDepth}-bit TrueColor` : 'unknown'

  return `${width}×${height} ${aspectRatio}:10 ${colorMode} (viewport: ${viewportWidth}×${viewportHeight})`
})

// Internationalization API
const dateFormatter = new Intl.DateTimeFormat(navigator.language, {
  weekday: 'long',
  year: 'numeric',
  month: 'long',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
  second: '2-digit',
  timeZoneName: 'long',
})

const i18nDateTimeFormat = computed(() => {
  return dateFormatter.format(new Date())
})

const i18nResolvedOptions = computed(() => {
  return dateFormatter.resolvedOptions()
})

// Navigator specific properties
const devicePostureType = computed(() => {
  if (navigator.devicePosture && navigator.devicePosture.type) {
    return navigator.devicePosture.type
  }
  return 'undefined'
})

const pdfViewerEnabled = computed(() => {
  if (navigator.pdfViewerEnabled !== undefined) {
    return navigator.pdfViewerEnabled
  }
  return 'undefined'
})

const globalPrivacyControl = computed(() => {
  if (navigator.globalPrivacyControl !== undefined) {
    return navigator.globalPrivacyControl
  }
  return 'undefined'
})

// Cache-related computed properties and functions
const totalCacheItems = computed(() => {
  return cacheStats.value.reduce((sum, cache) => sum + cache.count, 0)
})

const totalCacheSize = computed(() => {
  return cacheStats.value.reduce((sum, cache) => sum + cache.size, 0)
})

/**
 * Format bytes into human-readable size
 */
const formatSize = (bytes) => {
  if (bytes === 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(2)} ${units[i]}`
}

/**
 * Estimate size of a response object
 */
const estimateResponseSize = async (response) => {
  try {
    // Clone the response to not consume the original
    const clone = response.clone()

    // Get the response as array buffer to measure its size
    const buffer = await clone.arrayBuffer()
    return buffer.byteLength
  } catch (e) {
    console.warn('Failed to estimate response size:', e)
    return 0
  }
}

/**
 * Refresh cache statistics
 */
const refreshCacheStats = async () => {
  if (!cacheSupported.value) return

  isLoadingCache.value = true
  cacheStats.value = []

  try {
    // Get all cache names
    const cacheNames = await window.caches.keys()

    // Process each cache
    for (const name of cacheNames) {
      const cache = await window.caches.open(name)
      const requests = await cache.keys()

      let totalSize = 0

      // Estimate the total size of cache entries
      for (const request of requests) {
        const response = await cache.match(request)
        if (response) {
          const size = await estimateResponseSize(response)
          totalSize += size
        }
      }

      cacheStats.value.push({
        name,
        count: requests.length,
        size: totalSize,
      })
    }
  } catch (e) {
    console.error('Error fetching cache stats:', e)
  } finally {
    isLoadingCache.value = false
  }
}

/**
 * Clear a specific cache
 */
const clearSingleCache = async (cacheName) => {
  if (!cacheSupported.value) return

  isLoadingCache.value = true
  try {
    await window.caches.delete(cacheName)
    await refreshCacheStats()
  } catch (e) {
    console.error(`Failed to delete cache ${cacheName}:`, e)
  } finally {
    isLoadingCache.value = false
  }
}

/**
 * Clear all caches
 */
const clearAllCaches = async () => {
  if (!cacheSupported.value) return

  isLoadingCache.value = true
  try {
    const cacheNames = await window.caches.keys()
    await Promise.all(cacheNames.map(name => window.caches.delete(name)))
    await refreshCacheStats()
  } catch (e) {
    console.error('Failed to clear all caches:', e)
  } finally {
    isLoadingCache.value = false
  }
}

// Load cache statistics when component is mounted
onMounted(() => {
  window.addEventListener('resize', handleResize)
  if (cacheSupported.value) {
    refreshCacheStats()
  }
  // fetch server debug info on mount
  fetchServerDebug()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', handleResize)
})
</script>

<style scoped>
.animate-rotate {
  transition: transform 0.2s ease;
}
</style>
