<template>
  <a
    v-if="isPlainAnchor"
    :href="url"
    :target="computedTarget"
    :rel="computedTarget === '_blank' ? 'noopener noreferrer' : null"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <slot />
  </a>
  <router-link
    v-else
    v-slot="{ href: routerHref, navigate }"
    :to="url"
    custom
  >
    <a
      :href="routerHref"
      v-bind="$attrs"
      v-on="$listeners"
      @click="handleNavigate($event, navigate)"
    >
      <slot />
    </a>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { isExternalUrl } from '@/helper/urls'

const props = defineProps({
  to: {
    type: [String, Object],
    default: null,
  },
  href: {
    type: String,
    default: null,
  },
  target: {
    type: String,
    default: null,
  },
  openExternalInNewTab: {
    type: Boolean,
    default: true,
  },
})

const url = computed(() => props.to || props.href)

const isExternal = computed(() => isExternalUrl(url.value))

/**
 * Targets the router cannot handle: external urls, in-page anchors and missing
 * targets (e.g. a link that is only there for its click handler).
 */
const isPlainAnchor = computed(() => {
  if (isExternal.value) return true
  const value = url.value
  if (!value) return true
  return typeof value === 'string' && (value.startsWith('#') || value.startsWith('javascript:'))
})

const computedTarget = computed(() => {
  if (props.target) return props.target
  if (isExternal.value && props.openExternalInNewTab) return '_blank'
  return '_self'
})

const handleNavigate = (event, navigate) => {
  navigate(event)
}
</script>

<script>
export default {
  name: 'FsLink',
  inheritAttrs: false,
}
</script>
