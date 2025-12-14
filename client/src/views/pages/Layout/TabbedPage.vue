<!-- This component provides a tabbed page layout with tabs in the left column and content in the main area -->
<template>
  <div>
    <!-- Mobile: List View -->
    <div v-if="isMobile" class="mobile-settings">
      <slot name="top" />

      <!-- Show list of tabs -->
      <div v-if="activeTabIndex === null">
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
            @click="activeTabIndex = index"
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
  return tabs.value[activeTabIndex.value]?.title || ''
})

const checkMobile = () => {
  isMobile.value = window.innerWidth <= props.mobileBreakpoint
}

const selectTab = (index) => {
  activeTabIndex.value = index
  window.scrollTo(0, 0)
  emit('tab-change', index)
}

watch(() => props.initialTab, (newTab) => {
  if (newTab !== null && newTab !== undefined) {
    activeTabIndex.value = newTab
  }
}, { immediate: true })

watch(activeTabIndex, (newIndex) => {
  if (newIndex !== null && newIndex >= 0) {
    emit('tab-change', newIndex)
  }
})

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)

  // Set initial active tab
  const initialActiveIndex = tabs.value.findIndex(tab => tab.active)
  if (initialActiveIndex >= 0) {
    activeTabIndex.value = initialActiveIndex
  } else if (tabs.value.length > 0) {
    // Default to first tab on desktop, null (list view) on mobile
    activeTabIndex.value = isMobile.value ? null : 0
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
