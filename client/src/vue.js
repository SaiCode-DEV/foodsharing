import Vue from 'vue'
import { i18nInstance } from '@/helper/i18n'
import dateFormatter from '@/helper/date-formatter'
import { url } from '@/helper/urls'
import { isFeatureToggleActive } from '@/helper/featuretoggles'
import BootstrapVue from 'bootstrap-vue'
import { createPinia, PiniaVuePlugin } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { useEnvironmentCheck } from '@/composables/useEnvironmentCheck'
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

export function vueApply (selector, disableElNotFoundException = true) {
  let elements = document.querySelectorAll(selector)

  // querySelectorAll().forEach() is broken in iOS 9
  elements = Array.from(elements)

  if (!elements.length) {
    if (disableElNotFoundException) {
      return
    }
    const { isProd } = useEnvironmentCheck()
    if (!isProd) { console.error(`vueUse-Error: no elements were found with selector '${selector}'`) }
  }
  elements.forEach((el, index) => {
    // If this element is already mounted by Vue, skip it silently
    if (el && el.__vue__) {
      return
    }
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
