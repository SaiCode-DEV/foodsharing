<template>
  <div class="responsive-tabs">
    <!-- Mobile: List View -->
    <div v-if="isMobile" class="mobile-settings">
      <!-- Show list of tabs -->
      <div v-if="selectedTabIndex === null">
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
      <div v-else class="mobile-page-content">
        <div class="mobile-page-header">
          <b-button
            variant="link"
            @click="selectedTabIndex = null"
          >
            <i class="fas fa-arrow-left" />
          </b-button>
          <h5 class="mb-0">
            {{ currentTabTitle }}
          </h5>
        </div>
        <div class="page-content-wrapper">
          <component
            :is="{ functional: true, render: tabs[selectedTabIndex].renderFn }"
          />
        </div>
      </div>
    </div>

    <!-- Desktop: List-style tabs -->
    <div v-else class="desktop-tabs">
      <div class="tabs-container">
        <b-list-group class="settings-list">
          <b-list-group-item
            v-for="(tab, index) in tabs"
            :key="index"
            button
            :active="activeTabIndex === index"
            @click="activeTabIndex = index"
          >
            {{ tab.title }}
          </b-list-group-item>
        </b-list-group>
        <div class="tab-content">
          <component
            :is="{ functional: true, render: tabs[activeTabIndex].renderFn }"
            v-if="tabs[activeTabIndex]"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, useSlots, defineProps, defineEmits } from 'vue'

const props = defineProps({
  initialTab: {
    type: Number,
    default: null,
  },
  mobileBreakpoint: {
    type: Number,
    default: 768,
  },
})

const emit = defineEmits(['tab-change'])

const slots = useSlots()
const isMobile = ref(false)
const selectedTabIndex = ref(null)
const activeTabIndex = ref(0)

const tabs = computed(() => {
  const defaultSlot = slots.default?.() || []

  return defaultSlot
    .filter(vnode => {
      // Vue 2 uses tag property with format "vue-component-{id}-{ComponentName}"
      const tag = vnode.tag || ''

      return tag.includes('ResponsiveTab') || tag.includes('BTab')
    })
    .map(vnode => {
      // In Vue 2, props are in componentOptions.propsData
      const propsData = vnode.componentOptions?.propsData || vnode.data?.attrs || {}
      const children = vnode.componentOptions?.children || []

      // Create a render function that returns the children
      const renderFn = (h) => {
        if (children.length === 1) {
          return children[0]
        }
        return h('div', children)
      }

      return {
        title: propsData.title || '',
        active: propsData.active || false,
        renderFn,
        props: propsData,
      }
    })
})

const currentTabTitle = computed(() => {
  return tabs.value[selectedTabIndex.value]?.title || ''
})

const checkMobile = () => {
  isMobile.value = window.innerWidth <= props.mobileBreakpoint
}

const selectTab = (index) => {
  selectedTabIndex.value = index
  window.scrollTo(0, 0)
  emit('tab-change', index)
}

watch(() => props.initialTab, (newTab) => {
  if (newTab !== null && newTab !== undefined) {
    if (isMobile.value) {
      selectedTabIndex.value = newTab
    } else {
      activeTabIndex.value = newTab
    }
  }
}, { immediate: true })

watch(activeTabIndex, (newIndex) => {
  if (!isMobile.value && newIndex >= 0) {
    emit('tab-change', newIndex)
  }
})

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)

  // Set initial active tab
  const initialActiveIndex = tabs.value.findIndex(tab => tab.active)
  if (initialActiveIndex >= 0) {
    if (isMobile.value) {
      selectedTabIndex.value = initialActiveIndex
    } else {
      activeTabIndex.value = initialActiveIndex
    }
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<style lang="scss" scoped>
.mobile-settings {
  .settings-list {
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

  .mobile-page-content {
    .mobile-page-header {
      display: flex;
      align-items: center;
      padding: 1rem;
      border-bottom: 1px solid var(--fs-color-primary-500);
      position: sticky;
      top: 0;
      z-index: 10;

      h5 {
        flex: 1;
        font-weight: 600;
      }
    }

    .page-content-wrapper {
      padding: 1rem;
    }
  }
}

.desktop-tabs {
  .tabs-container {
    display: flex;
    gap: 1rem;

    .settings-list {
      flex: 0 0 250px;
      min-width: 250px;
    }

    .tab-content {
      flex: 1;
      min-width: 0;
    }
  }
}
</style>
