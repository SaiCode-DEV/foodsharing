import Vue from 'vue'
import { i18nInstance } from '@/helper/i18n'
import dateFormatter from '@/helper/date-formatter'
import { url } from '@/helper/urls'
import { isFeatureToggleActive } from '@/helper/featuretoggles'
import BootstrapVue from 'bootstrap-vue'
import { createPinia, PiniaVuePlugin } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import Notifications from 'vue-notification'

Vue.use(BootstrapVue)
Vue.use(PiniaVuePlugin)
const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)
Vue.use(pinia)
Vue.use(Notifications)
Vue.use(i18nInstance)

Vue.prototype.$url = url
Vue.prototype.$dateFormatter = dateFormatter
Vue.prototype.$isFeatureToggleActive = isFeatureToggleActive
Vue.prototype.$confirmationDialogue = function (messageKey, options = {}) {
  options = Object.assign({
    title: this.$t('are_you_sure'),
    okVariant: 'danger',
    okTitle: this.$t('button.delete'),
    cancelTitle: this.$t('button.cancel'),
    centered: true,
  }, options)
  return this.$bvModal.msgBoxConfirm(this.$t(messageKey), options)
}

export function vueRegister (components) {
  for (const key in components) {
    Vue.component(key, components[key])
  }
}

export function vueApply (selector, disableElNotFoundException = false) {
  // If requesting the global notifications wrapper, ensure a fallback mount
  // point exists on body
  const selectorId = selector.replace('#', '')
  // Only run fallback mount point creation if page is not /karte and does not start with /msg
  const path = typeof window !== 'undefined' ? window.location.pathname : ''
  const isBrowser = typeof document !== 'undefined'
  const notMounted = !document.getElementById(selectorId)

  // Only create a fallback mount point for global wrappers on pages
  // where it is safe — skip on the map page (`/karte`) and the
  // messages pages (`/msg*`) because those have special rendering
  // behaviour and creating a body-level mount can interfere with them.
  const shouldNotCreateFallbackMountPoint = (path === '/karte' || path.startsWith('/msg')) && selectorId === 'vue-footer'
  if (isBrowser && notMounted && !shouldNotCreateFallbackMountPoint) {
    const host = document.createElement('div')
    host.id = selectorId
    host.className = 'vue-wrapper'
    // Construct component name from selector (e.g. #vue-ui-notifications ->
    // UiNotifications)
    const componentName = selectorId.replace('vue-', '').split('-').map((word, index) => {
      return word.charAt(0).toUpperCase() + word.slice(1)
    }).join('')
    console.log('vueApply: creating fallback mount point for', selector, 'with component', componentName)
    host.setAttribute('data-vue-component', componentName)
    host.setAttribute('data-vue-props', '{}')
    document.body.appendChild(host)
  }

  let elements = document.querySelectorAll(selector)

  // querySelectorAll().forEach() is broken in iOS 9
  elements = Array.from(elements)

  if (!elements.length) {
    if (disableElNotFoundException) {
      return
    }
    throw new Error(`vueUse-Error: no elements were found with selector '${selector}'`)
  }
  elements.forEach((el, index) => {
    const componentName = el.getAttribute('data-vue-component')
    let propsStr = el.getAttribute('data-vue-props')
    propsStr = propsStr.replace(/\n/g, '\\n').replace(/\r/g, '\\r').replace(/\t/g, '\\t')
    const props = JSON.parse(propsStr) || {}
    const initialData = JSON.parse(el.getAttribute('vue-initial-data')) || {}

    if (!componentName) {
      throw new Error('vueUse-Error: missing component name. pass it as <div data-vue-component="my-component" />')
    }

    const vm = new Vue({
      el,
      render (h) {
        return h(componentName, { props })
      },
      i18n: i18nInstance,
      pinia,
    })
    if (initialData && typeof initialData === 'object') {
      for (const key in initialData) {
        if (typeof vm.$children[0][key] === 'undefined' || typeof vm.$children[0][key] === 'function') {
          throw new Error(`vueUse() Error: prop '${key}' needs to be defined in data()`)
        }
        vm.$children[0][key] = initialData[key]
      }
    }
  })
}
