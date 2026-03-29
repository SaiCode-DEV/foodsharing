<!-- This component provides a tabbed page layout with tabs in the left column and content in the main area -->
<template>
  <div>
    <!-- Mobile: List View -->
    <div v-if="isMobile" class="mobile-settings">
      <slot name="top" />

      <!-- Show list of tabs -->
      <div v-if="activeTabIndex === null || !tabs[activeTabIndex]">
        <b-list-group>
          <b-list-group-item
            v-for="(tab, index) in tabs"
            :key="index"
            button
            class="d-flex justify-content-between align-items-center"
            @click="selectTab(index)"
          >
            {{ tab.title }}
            <i class="fas fa-chevron-right" />
          </b-list-group-item>
        </b-list-group>
      </div>

      <!-- Show selected tab content -->
      <Container
        v-else
        class="mobile-page-content"
        :collapsible="false"
      >
        <template #title>
          <div class="mobile-page-header">
            <b-button
              variant="link"
              @click="activeTabIndex = null"
            >
              <i class="fas fa-arrow-left" />
            </b-button>
            <h4 class="mb-0">
              {{ currentTabTitle }}
            </h4>
          </div>
        </template>
        <div class="page-content-wrapper">
          <component
            :is="{ functional: true, render: tabs[activeTabIndex].renderFn }"
          />
        </div>
      </Container>
    </div>

    <!-- Desktop: BasePage Layout -->
    <BasePage v-else :wide-cols="true">
      <template #top>
        <slot name="top" />
      </template>

      <template #left>
        <b-list-group class="settings-list">
          <b-list-group-item
            v-for="(tab, index) in tabs"
            :key="index"
            button
            :active="activeTabIndex === index"
            @click="selectTab(index)"
          >
            {{ tab.title }}
          </b-list-group-item>
        </b-list-group>
      </template>

      <template #default>
        <b-card>
          <component
            :is="{ functional: true, render: tabs[activeTabIndex].renderFn }"
            v-if="tabs[activeTabIndex]"
            class="ma-2"
          />
        </b-card>
      </template>
    </BasePage>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, useSlots, defineProps, defineEmits } from 'vue'
import BasePage from './BasePage.vue'
import Container from '@/components/Container/Container.vue'

const props = defineProps({
  initialTab: {
    type: Number,
    default: null,
  },
  mobileBreakpoint: {
    type: Number,
    default: 995,
  },
})

const emit = defineEmits(['tab-change'])

const slots = useSlots()
const isMobile = ref(false)
const activeTabIndex = ref(null)

function collectTabVnodes () {
  const defaultSlot = slots.default?.() || []
  const tabVnodes = defaultSlot.filter(vnode => {
    // Vue 2 uses tag property with format "vue-component-{id}-{ComponentName}"
    const tag = vnode.tag || ''

    return tag.includes('ResponsiveTab') || tag.includes('BTab')
  })

  // Ensure titles are unique to avoid issues with dynamic content and reactivity
  const seenTitles = new Set(tabVnodes.map(v => getPropsFromVNode(v).title))
  if (seenTitles.size !== tabVnodes.length) {
    throw new Error('Duplicate tab title detected. Tab titles must be unique!')
  }

  return tabVnodes
}

function getPropsFromVNode (vnode) {
  return vnode.componentOptions?.propsData || vnode.data?.attrs || {}
}

const tabs = computed(() => {
  return collectTabVnodes()
    .map(vnode => {
      // In Vue 2, props are in componentOptions.propsData
      // In Vue 3, props are in data.attrs
      const tabProps = getPropsFromVNode(vnode)

      // Create a render function that returns the children of this tab
      const tabContentRenderFn = (h) => {
        // Resolve the current tab children each time the tab is rendered.
        // Otherwise, slot changes (components or props) won't be reflected in the tab content.
        const currentTabVnode = collectTabVnodes().find(v => {
          const props = getPropsFromVNode(v)
          return props.title === tabProps.title
        })

        const tabChildren = currentTabVnode?.componentOptions?.children || []

        if (tabChildren.length === 1) {
          return tabChildren[0]
        }
        return h('div', tabChildren)
      }

      return {
        title: tabProps.title || '',
        active: tabProps.active || false,
        renderFn: tabContentRenderFn,
        props: tabProps,
      }
    })
})

const currentTabTitle = computed(() => {
  return tabs.value[activeTabIndex.value]?.title || ''
})

const checkMobile = () => {
  isMobile.value = window.innerWidth <= props.mobileBreakpoint
  if (!isMobile.value && activeTabIndex.value === null) {
    // If switching to desktop view, default to first tab if no tab is active
    activeTabIndex.value = 0
  }
}

const selectTab = (index) => {
  activeTabIndex.value = index
  if (isMobile.value) {
    window.scrollTo(0, 0)
  }
  emit('tab-change', index)
}

// Override active tab if initialTab prop changes
watch(() => props.initialTab, (newTab) => {
  if (newTab !== null && newTab !== undefined) {
    activeTabIndex.value = newTab
  }
})

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)

  // Set initial active tab
  if (props.initialTab !== null && props.initialTab !== undefined) {
    // First, initialTab prop takes precedence if set
    activeTabIndex.value = props.initialTab
  } else {
    // Next, check if there is a tab with the "active" prop
    const initialActiveIndex = tabs.value.findIndex(tab => tab.active)
    if (initialActiveIndex >= 0) {
      activeTabIndex.value = initialActiveIndex
    } else {
      // Default to first tab on desktop, null (list view) on mobile
      activeTabIndex.value = isMobile.value ? null : 0
    }
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style lang="scss" scoped>
.settings-list {
  border-radius: 0.25rem;
  :deep(.list-group-item) {
    padding: 1rem 1.25rem;
    font-size: 1rem;
    border-left: none;
    border-right: none;
    &:first-child {
      border-top: none;
    }
    &:last-child {
      border-bottom: none;
    }
  }
}

.mobile-settings {
  .mobile-page-content {
    .mobile-page-header {
      display: flex;
      align-items: center;
      padding: 0.25rem 0;
      h4 {
        flex: 1;
        font-weight: 600;
      }
    }

    .page-content-wrapper {
      padding: 1rem;
    }
  }
}
</style>
