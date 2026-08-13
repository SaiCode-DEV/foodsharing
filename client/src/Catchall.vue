<template>
  <!-- eslint-disable-next-line vue/no-v-html -->
  <div id="app-content" v-html="htmlContent" />
</template>

<script setup>
import { computed, watch, getCurrentInstance, nextTick } from 'vue'
import { resetVueApplyQueue, flushVueApplyQueue, vueApply, destroyActiveVueInstances } from '@/vue'

const instance = getCurrentInstance()
const route = computed(() => instance.proxy.$route)

const htmlContent = computed(() => {
  // Get the HTML content from the route meta
  return route.value?.meta?.content || ''
})

// Execute scripts and initialize Vue components after content is rendered
const initializeContent = async () => {
  await nextTick()

  // Find and execute inline scripts in the loaded content
  const appContent = document.getElementById('app-content')

  if (appContent) {
    // Look for vue-wrapper elements and manually initialize them
    const vueWrappers = appContent.querySelectorAll('.vue-wrapper')

    // Load external stylesheets
    const stylesheets = Array.from(appContent.querySelectorAll('.content-stylesheet'))
    stylesheets.forEach((link) => {
      const href = link.getAttribute('href')
      link.remove() // Remove from app-content
      if (href && !document.querySelector(`link[href="${href}"]`)) {
        const newLink = document.createElement('link')
        newLink.rel = 'stylesheet'
        newLink.type = 'text/css'
        newLink.href = href
        document.head.appendChild(newLink)
      }
    })

    // Load external scripts sequentially
    const scripts = Array.from(appContent.querySelectorAll('.content-script'))
    if (!window.__loadedScripts) {
      window.__loadedScripts = new Map()
    }

    for (const script of scripts) {
      const src = script.getAttribute('src')
      script.remove() // Remove dead script from app-content

      if (src && !window.__loadedScripts.has(src)) {
        if (document.querySelector(`script[src="${src}"]`)) {
          window.__loadedScripts.set(src, Promise.resolve())
        } else {
          window.__loadedScripts.set(src, new Promise((resolve, reject) => {
            const newScript = document.createElement('script')
            newScript.src = src
            newScript.onload = resolve
            newScript.onerror = reject
            document.head.appendChild(newScript)
          }))
        }
      }
      try {
        await window.__loadedScripts.get(src)
      } catch (error) {
        console.error('[Catchall] Failed to load script:', src, error)
      }
    }

    // Execute inline scripts
    const inlineScripts = Array.from(appContent.querySelectorAll('.content-inline-script'))
    inlineScripts.forEach((script) => {
      const content = script.textContent
      script.remove() // Remove from app-content
      const newScript = document.createElement('script')
      newScript.textContent = content
      document.body.appendChild(newScript)
      document.body.removeChild(newScript)
    })

    // Mark content as ready first
    flushVueApplyQueue()

    // Manually trigger DOMContentLoaded so legacy scripts can initialize. This has to happen
    // before the wrappers are mounted, because a script may register its components in there.
    window.document.dispatchEvent(new Event('DOMContentLoaded', {
      bubbles: true,
      cancelable: true,
    }))

    // Apply Vue to the wrappers that the page's script did not mount itself
    vueWrappers.forEach((wrapper) => {
      const selector = '#' + wrapper.id
      try {
        vueApply(selector, true)
      } catch (error) {
        console.error('[Catchall] Failed to initialize Vue component:', selector, error)
      }
    })
  } else {
    console.warn('[Catchall] appContent element not found!')
  }
}

watch(htmlContent, async (newContent) => {
  destroyActiveVueInstances()
  if (newContent) {
    // Reset queue before loading new content
    resetVueApplyQueue()
    await initializeContent()
  }
}, { immediate: true })

</script>

<style scoped>
#app-content {
  /* Ensure content takes full width */
  width: 100%;
}
</style>
